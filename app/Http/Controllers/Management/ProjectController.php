<?php

namespace App\Http\Controllers\Management;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Concerns\BuildsProjectOverview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\DelegateProjectRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use BuildsProjectOverview;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        $projects = Project::query()
            ->with(['salesOrder:id,number,contact_id', 'salesOrder.contact:id,name', 'salesOrder.lines:id,sales_order_id,category,qty', 'delegatedTo:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $projects->through(fn (Project $p) => $this->projectOverviewRow($p));

        return Inertia::render('Projects/Overview/Index', [
            'projects' => $projects,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statusOptions' => ProjectStatus::options(),
            'role' => 'management',
        ]);
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);

        return Inertia::render('Projects/Overview/Show', [
            'project' => $this->projectOverviewDetail($project),
            'canDelegate' => request()->user()->can('delegate', $project),
            'projectManagerOptions' => User::query()
                ->where('role', 'project_manager')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function delegate(DelegateProjectRequest $request, Project $project): RedirectResponse
    {
        $pmId = $request->validated('project_manager_id');

        $project->update([
            'delegated_to' => $pmId,
            'delegated_by' => $pmId ? $request->user()->id : null,
            'delegated_at' => $pmId ? now() : null,
        ]);

        return back()->with('success', $pmId ? 'Project didelegasikan.' : 'Delegasi project ditarik kembali.');
    }
}
