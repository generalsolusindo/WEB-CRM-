<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarehouseItem;

class WarehouseItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isWarehouse($user) || $this->isProcurement($user);
    }

    public function create(User $user): bool
    {
        return $this->isWarehouse($user);
    }

    public function update(User $user, WarehouseItem $warehouseItem): bool
    {
        return $this->isWarehouse($user);
    }

    public function delete(User $user, WarehouseItem $warehouseItem): bool
    {
        return $this->isWarehouse($user);
    }

    private function isWarehouse(User $user): bool
    {
        return $user->role === 'warehouse' && $user->is_active;
    }

    private function isProcurement(User $user): bool
    {
        return $user->role === 'procurement' && $user->is_active;
    }
}
