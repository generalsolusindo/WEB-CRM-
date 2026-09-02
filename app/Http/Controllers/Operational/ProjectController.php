<?php

namespace App\Http\Controllers\Operational;

use App\Actions\Operational\MarkProjectReady;
use App\Enums\ActualProcurementStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\PlanningRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));

        $projects = Project::query()
            ->with(['salesOrder:id,number,contact_id', 'salesOrder.contact:id,name,company_name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereHas('salesOrder', fn ($so) => $so
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('contact', fn ($c) => $c
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Operational/Projects/Index', [
            'projects' => $projects,
            'filters' => ['search' => $search, 'status' => $filters['status'] ?? ''],
            'statusOptions' => ProjectStatus::options(),
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load([
            'salesOrder:id,number,order_type,payment_rule,contact_id,po_number',
            'salesOrder.contact:id,name,company_name,email,phone,address,npwp',
            'salesOrder.lines:id,sales_order_id,item_name,description,qty,unit',
            'creator:id,name',
            'actualProcurements' => fn ($q) => $q->orderBy('id'),
            'actualProcurements.vendor:id,name',
            'technicians.technician:id,name',
            'tasks' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('id'),
            'tasks.attachments:id,attachable_type,attachable_id,category,file_path,created_at',
            'bastRecords' => fn ($q) => $q->latest(),
            'bastRecords.submitter:id,name',
            'bastRecords.verifier:id,name',
            'bastRecords.attachments:id,attachable_type,attachable_id,category,file_path,created_at',
            'changeRequests' => fn ($q) => $q->latest(),
            'changeRequests.requestedBy:id,name',
        ]);

        $user = request()->user();

        $bastRecords = $project->bastRecords->map(fn ($bast) => [
            'id' => $bast->id,
            'status' => $bast->status,
            'notes' => $bast->notes,
            'submitted_at' => $bast->submitted_at,
            'submitter' => $bast->submitter?->only('name'),
            'documents' => $bast->attachments
                ->where('category', 'bast_document')
                ->map(fn ($a) => ['id' => $a->id, 'url' => \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay())])
                ->values(),
        ]);

        $taskPhotos = $project->tasks->mapWithKeys(fn ($task) => [
            $task->id => $task->attachments
                ->whereIn('category', ['task_before', 'task_after'])
                ->map(fn ($a) => ['id' => $a->id, 'category' => $a->category, 'url' => \Illuminate\Support\Facades\Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay())])
                ->values(),
        ]);

        $procurementItems = $project->actualProcurements;
        $received = $procurementItems->where('status', ActualProcurementStatus::Received->value)->count();

        return Inertia::render('Operational/Projects/Show', [
            'project' => $project,
            'approvalDocs' => \App\Services\Sales\CustomerApprovalDocs::of($project->salesOrder),
            'bastRecords' => $bastRecords,
            'taskPhotos' => $taskPhotos,
            'procurementProgress' => ['received' => $received, 'total' => $procurementItems->count()],
            'statusOptions' => ProjectStatus::options(),
            'availabilityOptions' => ActualProcurementStatus::options(),
            'technicianOptions' => User::query()
                ->where('role', 'technician')->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
            'changeRequestTypes' => \App\Enums\ChangeRequestType::options(),
            'permissions' => [
                'plan' => $user->can('update', $project),
                'manageResources' => $user->can('manageResources', $project),
                'manageTasks' => $user->can('manageTasks', $project),
                'markReady' => $user->can('markReady', $project),
                'start' => $user->can('start', $project),
                'verifyBast' => $user->can('verifyBast', $project),
                'completeDirect' => $user->can('completeDirect', $project->loadMissing('salesOrder')),
                'manageChangeRequests' => $user->can('manageChangeRequests', $project),
            ],
        ]);
    }

    public function planning(PlanningRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $project->update([
            ...$request->validated(),
            'status' => $project->status === ProjectStatus::Draft->value
                ? ProjectStatus::Planning->value
                : $project->status,
        ]);

        return back()->with('success', 'Planning project tersimpan.');
    }

    public function markReady(Project $project, MarkProjectReady $action): RedirectResponse
    {
        Gate::authorize('markReady', $project);
        $action->handle($project);

        return back()->with('success', 'Project siap dijalankan.');
    }

    public function start(Project $project): RedirectResponse
    {
        Gate::authorize('start', $project);

        DB::transaction(function () use ($project) {
            $locked = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === ProjectStatus::Ready->value, 409);
            $locked->update(['status' => ProjectStatus::InProgress->value]);
        });

        return back()->with('success', 'Project dimulai.');
    }

    // TODO: alur final Material Only masih tentatif — sementara Operational menyelesaikan
    // langsung tanpa BAST.
    public function complete(Project $project): RedirectResponse
    {
        Gate::authorize('completeDirect', $project->loadMissing('salesOrder'));

        DB::transaction(function () use ($project) {
            $locked = Project::query()->with('salesOrder')->whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_unless(
                $locked->status === ProjectStatus::InProgress->value
                    && $locked->salesOrder->order_type === 'material_only',
                409,
            );
            $locked->update(['status' => ProjectStatus::Completed->value]);
        });

        return back()->with('success', 'Project ditandai selesai.');
    }
}
