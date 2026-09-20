<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\VendorServicePayment;

class VendorServicePaymentPolicy
{
    /** Procurement mengisi / mengubah deal vendor jasa untuk sebuah project. */
    public function manage(User $user, Project $project): bool
    {
        if ($user->role !== 'procurement' || ! $user->is_active || $project->status === 'completed') {
            return false;
        }

        $existing = $project->vendorServicePayment;

        return $existing === null || $existing->isEditable();
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['finance', 'management'], true);
    }

    public function view(User $user, VendorServicePayment $payment): bool
    {
        return $user->is_active && in_array($user->role, ['procurement', 'finance', 'operational', 'management'], true);
    }

    /** Catat / batalkan transfer ke vendor — Finance. */
    public function pay(User $user, VendorServicePayment $payment): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }
}
