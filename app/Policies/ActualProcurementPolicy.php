<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\ActualProcurement;
use App\Models\User;

class ActualProcurementPolicy
{
    /**
     * Ubah vendor / cost / status pengadaan — HANYA Procurement, selama project belum selesai.
     */
    public function update(User $user, ActualProcurement $actualProcurement): bool
    {
        return $user->role === 'procurement'
            && $user->is_active
            && $actualProcurement->loadMissing('project')->project->status !== ProjectStatus::Completed->value;
    }
}
