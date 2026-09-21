<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Attachment;
use App\Models\ProjectTask;
use App\Models\User;

class ProjectTaskPolicy
{
    public function view(User $user, ProjectTask $task): bool
    {
        return $this->isProjectMember($user, $task);
    }

    /**
     * Ubah status kerja (pending/in_progress/done) — hanya anggota tim, hanya saat
     * project berjalan.
     */
    public function updateStatus(User $user, ProjectTask $task): bool
    {
        return $this->isProjectMember($user, $task)
            && $task->project->status === ProjectStatus::InProgress->value
            && $task->project->hasCheckedIn($user);
    }

    public function uploadPhoto(User $user, ProjectTask $task): bool
    {
        return $this->updateStatus($user, $task);
    }

    /** Hapus foto: hanya pengunggahnya, selama project berjalan dan tugas belum Selesai (supaya syarat foto minimal tidak bisa ditembus). */
    public function deletePhoto(User $user, ProjectTask $task, Attachment $attachment): bool
    {
        return $this->updateStatus($user, $task)
            && $task->status !== TaskStatus::Done->value
            && $attachment->uploaded_by === $user->id
            && $attachment->attachable_type === $task->getMorphClass()
            && $attachment->attachable_id === $task->id
            && in_array($attachment->category, ['task_before', 'task_after'], true);
    }

    private function isProjectMember(User $user, ProjectTask $task): bool
    {
        if (! $user->canWorkAsTechnician()) {
            return false;
        }

        return $task->loadMissing('project')->project
            ->technicians()
            ->where('technician_id', $user->id)
            ->exists();
    }
}
