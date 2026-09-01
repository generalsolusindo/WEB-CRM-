<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSales($user);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation);
    }

    public function create(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $this->isSales($user)
            && $procurementRequest->status === 'ready'
            && $procurementRequest->lead()->where('sales_id', $user->id)->exists();
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Draft->value;
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $this->update($user, $quotation)
            && ! $quotation->revisions()->exists()
            && ! $quotation->salesOrder()->exists();
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $this->update($user, $quotation);
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && in_array($quotation->status, [
                QuotationStatus::Sent->value,
                QuotationStatus::Rejected->value,
            ], true)
            && ! $quotation->revisions()->exists()
            && ! $quotation->salesOrder()->exists();
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Sent->value
            && ! $quotation->salesOrder()->exists();
    }

    public function confirm(User $user, Quotation $quotation): bool
    {
        return $this->owns($user, $quotation)
            && $quotation->status === QuotationStatus::Sent->value
            && ! $quotation->salesOrder()->exists();
    }

    private function owns(User $user, Quotation $quotation): bool
    {
        return $this->isSales($user) && $quotation->sales_id === $user->id;
    }

    private function isSales(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }
}
