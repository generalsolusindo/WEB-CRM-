<?php

namespace App\Policies;

use App\Models\DeliveryNote;
use App\Models\User;

class DeliveryNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isOperational($user);
    }

    /** Operational (semua), atau anggota tim project terkait (teknisi). */
    public function view(User $user, DeliveryNote $deliveryNote): bool
    {
        if ($this->isOperational($user)) {
            return true;
        }

        return $this->isProjectMember($user, $deliveryNote);
    }

    public function create(User $user): bool
    {
        return $this->isOperational($user);
    }

    /** Hanya leader tim project, hanya selama status masih "sent". */
    public function receive(User $user, DeliveryNote $deliveryNote): bool
    {
        return $deliveryNote->status === 'sent' && $this->isProjectLeader($user, $deliveryNote);
    }

    /**
     * Bukti diterima customer — opsional, diupload Operational kapan saja
     * (dokumentasi tambahan, tidak menggantikan alur `receive` teknisi di atas).
     */
    public function uploadReceivedProof(User $user, DeliveryNote $deliveryNote): bool
    {
        return $this->isOperational($user);
    }

    private function isOperational(User $user): bool
    {
        return $user->role === 'operational' && $user->is_active;
    }

    private function isProjectMember(User $user, DeliveryNote $deliveryNote): bool
    {
        if ($user->role !== 'technician' || ! $user->is_active) {
            return false;
        }

        $project = $deliveryNote->salesOrder?->projects()->first();

        return $project && $project->technicians()->where('technician_id', $user->id)->exists();
    }

    private function isProjectLeader(User $user, DeliveryNote $deliveryNote): bool
    {
        if ($user->role !== 'technician' || ! $user->is_active) {
            return false;
        }

        $project = $deliveryNote->salesOrder?->projects()->first();

        return $project && $project->technicians()->where('technician_id', $user->id)->where('is_leader', true)->exists();
    }
}
