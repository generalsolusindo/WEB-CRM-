<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead)
            && ! $lead->procurementRequests()->exists();
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead)
            && ! $lead->procurementRequests()->exists();
    }

    public function convert(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    public function submitToProcurement(User $user, Lead $lead): bool
    {
        return $this->owns($user, $lead);
    }

    private function owns(User $user, Lead $lead): bool
    {
        return $user->role === 'sales'
            && $user->is_active
            && $lead->sales_id === $user->id;
    }
}
