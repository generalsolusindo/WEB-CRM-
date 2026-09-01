<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isProcurement($user);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->isProcurement($user);
    }

    public function create(User $user): bool
    {
        return $this->isProcurement($user);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->isProcurement($user);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $this->isProcurement($user);
    }

    private function isProcurement(User $user): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }
}
