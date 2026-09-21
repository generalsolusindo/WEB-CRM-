<?php

namespace App\Http\Controllers\Technician;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\CheckInProjectRequest;
use App\Http\Requests\Technician\CheckOutProjectRequest;
use App\Http\Requests\Technician\UploadTaskPhotoRequest;
use App\Models\Attachment;
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
                'checked_out' => $project->hasCheckedOut($user),
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
            'attachments:id,attachable_type,attachable_id,category,file_path,caption,uploaded_by,created_at',
        ]);

        $photos = $task->attachments
            ->whereIn('category', ['task_before', 'task_after'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'category' => $a->category,
                'caption' => $a->caption,
                'can_delete' => $user->can('deletePhoto', [$task, $a]),
                'url' => Storage::disk('local')->temporaryUrl($a->file_path, now()->addDay()),
            ])
            ->values();

        $checkedIn = $task->project->hasCheckedIn($user);
        $checkedOut = $task->project->hasCheckedOut($user);
        $mySelfie = $checkedIn
            ? $task->project->attachments()
                ->where('category', 'checkin_selfie')
                ->where('uploaded_by', $user->id)
                ->latest()
                ->first()
            : null;
        $myCheckoutSelfie = $checkedOut
            ? $task->project->attachments()
                ->where('category', 'checkout_selfie')
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
            'checkedOut' => $checkedOut,
            'canCheckOut' => $user->can('checkOut', $task->project),
            'checkoutSelfieUrl' => $myCheckoutSelfie ? Storage::disk('local')->temporaryUrl($myCheckoutSelfie->file_path, now()->addDay()) : null,
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

    public function checkOut(CheckOutProjectRequest $request, Project $project): RedirectResponse
    {
        $project->attachments()->create([
            'category' => 'checkout_selfie',
            'file_path' => $request->file('photo')->store('checkout-selfies'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Absen pulang tersimpan.');
    }

    public function updateStatus(Request $request, ProjectTask $task): RedirectResponse
    {
        Gate::authorize('updateStatus', $task);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        // Hanya field status yang boleh diubah technician — title/description/scheduled_date diabaikan.
        if ($data['status'] === TaskStatus::Done->value && ! $this->hasBeforeAndAfterPhotos($task)) {
            return back()->with('error', 'Tugas belum bisa diselesaikan. Unggah minimal 1 foto Before dan 1 foto After terlebih dahulu.');
        }

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
                'caption' => filled($request->validated('caption')) ? trim($request->validated('caption')) : null,
                'uploaded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', 'Foto tersimpan.');
    }

    public function deletePhoto(ProjectTask $task, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('deletePhoto', [$task, $attachment]);

        Storage::disk('local')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Foto dihapus.');
    }

    private function hasBeforeAndAfterPhotos(ProjectTask $task): bool
    {
        $categories = $task->attachments()->whereIn('category', ['task_before', 'task_after'])->pluck('category');

        return $categories->contains('task_before') && $categories->contains('task_after');
    }
}
