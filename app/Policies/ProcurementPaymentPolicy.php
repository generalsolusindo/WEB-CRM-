<?php

namespace App\Policies;

use App\Models\ProcurementPayment;
use App\Models\Project;
use App\Models\User;

class ProcurementPaymentPolicy
{
    /** Susun & ajukan pengajuan pembayaran pengadaan — Procurement. */
    public function submit(User $user, Project $project): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['procurement', 'project_manager', 'finance'], true);
    }

    public function view(User $user, ProcurementPayment $payment): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return match ($user->role) {
            'procurement', 'finance' => true,
            'project_manager' => $payment->project->delegated_to === $user->id,
            'management' => true,
            default => false,
        };
    }

    /** Setujui / tolak — HANYA Project Manager yang didelegasikan ke project ini. */
    public function review(User $user, ProcurementPayment $payment): bool
    {
        return $user->role === 'project_manager'
            && $user->is_active
            && $payment->project->delegated_to === $user->id;
    }

    /** Catat pembayaran ke vendor — Finance. */
    public function pay(User $user, ProcurementPayment $payment): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }

    /** Konfirmasi pembayaran sudah beres — Procurement. */
    public function confirm(User $user, ProcurementPayment $payment): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }
}
