<?php

namespace App\Actions\Technician;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Bast;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitBast
{
    /**
     * @param  array<int, UploadedFile>  $documents
     */
    public function handle(Project $project, User $leader, ?string $notes, array $documents): Bast
    {
        return DB::transaction(function () use ($project, $leader, $notes, $documents) {
            $locked = Project::query()
                ->with('tasks')
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ProjectStatus::InProgress->value) {
                throw ValidationException::withMessages([
                    'bast' => 'BAST hanya bisa dikirim saat project sedang berjalan.',
                ]);
            }

            if ($locked->tasks->isEmpty()
                || $locked->tasks->contains(fn ($task) => $task->status !== TaskStatus::Done->value)) {
                throw ValidationException::withMessages([
                    'bast' => 'Semua task harus berstatus Selesai sebelum mengirim BAST.',
                ]);
            }

            $bast = $locked->bastRecords()->create([
                'status' => 'submitted',
                'submitted_by' => $leader->id,
                'submitted_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($documents as $document) {
                $bast->attachments()->create([
                    'category' => 'bast_document',
                    'file_path' => $document->store('bast-documents'),
                    'uploaded_by' => $leader->id,
                ]);
            }

            $locked->update(['status' => ProjectStatus::Verification->value]);

            return $bast;
        });
    }
}
