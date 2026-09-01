<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
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
            && $task->project->status === ProjectStatus::InProgress->value;
    }

    public function uploadPhoto(User $user, ProjectTask $task): bool
    {
        return $this->updateStatus($user, $task);
    }

    private function isProjectMember(User $user, ProjectTask $task): bool
    {
        if ($user->role !== 'technician' || ! $user->is_active) {
            return false;
        }

        return $task->loadMissing('project')->project
            ->technicians()
            ->where('technician_id', $user->id)
            ->exists();
    }
}
