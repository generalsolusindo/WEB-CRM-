<?php

namespace App\Http\Controllers\Technician;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\CheckInProjectRequest;
use App\Http\Requests\Technician\UploadTaskPhotoRequest;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $user = $request->user();

        $projects = \App\Models\Project::query()
            ->whereHas('technicians', fn ($q) => $q->where('technician_id', $userId))
            ->with([
                'salesOrder:id,number,contact_id',
                'salesOrder.contact:id,name',
                'tasks' => fn ($q) => $q->orderBy('scheduled_date')->orderBy('id'),
                'technicians' => fn ($q) => $q->where('technician_id', $userId),
            ])
            ->latest()
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'number' => 'PRJ-'.str_pad((string) $project->id, 6, '0', STR_PAD_LEFT),
                'status' => $project->status,
                'customer' => $project->salesOrder->contact->name ?? '—',
                'sales_order' => $project->salesOrder->number,
                'is_leader' => (bool) optional($project->technicians->first())->is_leader,
                'checked_in' => $project->hasCheckedIn($user),
                'tasks' => $project->tasks->map(fn ($t) => [
                    'id' => $t->id, 'title' => $t->title, 'status' => $t->status,
                    'scheduled_date' => $t->scheduled_date,
                ]),
            ]);

        return Inertia::render('Technician/Tasks/Index', [
            'projects' => $projects,
            'statusOptions' => TaskStatus::options(),
        ]);
    }

    public function show(ProjectTask $task): Response
    {
        Gate::authorize('view', $task);

        $user = request()->user();

        $task->load([
            'project:id,status,sales_order_id',
            'project.salesOrder:id,number',
            'attachments:id,attachable_type,attachable_id,category,file_path,created_at',
        ]);

        $photos = $task->attachments
            ->whereIn('category', ['task_before', 'task_after'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'category' => $a->category,
                'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
            ])
            ->values();

        $checkedIn = $task->project->hasCheckedIn($user);
        $mySelfie = $checkedIn
            ? $task->project->attachments()
                ->where('category', 'checkin_selfie')
                ->where('uploaded_by', $user->id)
                ->latest()
                ->first()
            : null;

        return Inertia::render('Technician/Tasks/Show', [
            'task' => $task->only('id', 'title', 'description', 'scheduled_date', 'status'),
            'project' => [
                'id' => $task->project->id,
                'number' => 'PRJ-'.str_pad((string) $task->project->id, 6, '0', STR_PAD_LEFT),
                'status' => $task->project->status,
            ],
            'photos' => $photos,
            'statusOptions' => TaskStatus::options(),
            'canWork' => request()->user()->can('updateStatus', $task),
            'checkedIn' => $checkedIn,
            'canCheckIn' => $user->can('checkIn', $task->project),
            'selfieUrl' => $mySelfie ? Storage::disk('local')->temporaryUrl($mySelfie->file_path, now()->addDay()) : null,
        ]);
    }

    public function checkIn(CheckInProjectRequest $request, Project $project): RedirectResponse
    {
        $project->attachments()->create([
            'category' => 'checkin_selfie',
            'file_path' => $request->file('photo')->store('checkin-selfies'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Absen kehadiran tersimpan.');
    }

    public function updateStatus(Request $request, ProjectTask $task): RedirectResponse
    {
        Gate::authorize('updateStatus', $task);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        // Hanya field status yang boleh diubah technician — title/description/scheduled_date diabaikan.
        $task->update(['status' => $data['status']]);

        return back()->with('success', 'Status task diperbarui.');
    }

    public function uploadPhoto(UploadTaskPhotoRequest $request, ProjectTask $task): RedirectResponse
    {
        Gate::authorize('uploadPhoto', $task);

        foreach ($request->file('photos', []) as $photo) {
            $task->attachments()->create([
                'category' => $request->validated('category'),
                'file_path' => $photo->store('task-photos'),
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Foto tersimpan.');
    }
}
