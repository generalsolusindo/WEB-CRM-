<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

class BastPolicy
{
    /**
     * Submit BAST — HANYA leader tim technician project itu, saat project berjalan.
     */
    public function create(User $user, Project $project): bool
    {
        if (! $user->canWorkAsTechnician()) {
            return false;
        }

        if ($project->status !== ProjectStatus::InProgress->value) {
            return false;
        }

        return $project->technicians()
            ->where('technician_id', $user->id)
            ->where('is_leader', true)
            ->exists();
    }
}
