<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function create(User $user, Lead $lead): bool
    {
        return $this->ownsLead($user, $lead);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->ownsLead($user, $meeting->loadMissing('lead')->lead);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->ownsLead($user, $meeting->loadMissing('lead')->lead);
    }

    private function ownsLead(User $user, Lead $lead): bool
    {
        return $user->role === 'sales'
            && $user->is_active
            && $lead->sales_id === $user->id;
    }
}
