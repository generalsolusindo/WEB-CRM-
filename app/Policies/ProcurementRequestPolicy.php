<?php

namespace App\Policies;

use App\Enums\ProcurementRequestStatus;
use App\Models\ProcurementRequest;
use App\Models\User;

class ProcurementRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isProcurement($user);
    }

    public function view(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $this->isProcurement($user);
    }

    /**
     * Fulfillment (mengisi cost price, availability, dll) hanya boleh selama
     * PR masih dalam proses sourcing.
     */
    public function update(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $this->isProcurement($user)
            && in_array($procurementRequest->status, [
                ProcurementRequestStatus::Submitted->value,
                ProcurementRequestStatus::Searching->value,
            ], true);
    }

    public function start(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $this->isProcurement($user)
            && $procurementRequest->status === ProcurementRequestStatus::Submitted->value;
    }

    public function finalize(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $this->update($user, $procurementRequest);
    }

    private function isProcurement(User $user): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }
}
