<?php

namespace App\Policies;

use App\Models\Tax;
use App\Models\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function update(User $user, Tax $tax): bool
    {
        return $this->isAdministrator($user);
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $this->isAdministrator($user);
    }

    private function isAdministrator(User $user): bool
    {
        return $user->role === 'administrator' && $user->is_active;
    }
}
