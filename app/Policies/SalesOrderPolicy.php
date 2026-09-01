<?php

namespace App\Policies;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSales($user);
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $this->owns($user, $salesOrder);
    }

    /** Sales pemilik SO menambah / mengganti dokumen persetujuan customer & nomor PO. */
    public function manageDocuments(User $user, SalesOrder $salesOrder): bool
    {
        return $this->owns($user, $salesOrder)
            && $salesOrder->status !== SalesOrderStatus::Cancelled->value;
    }

    public function closeAsWon(User $user, SalesOrder $salesOrder): bool
    {
        return $this->owns($user, $salesOrder)
            && ! in_array($salesOrder->status, [
                SalesOrderStatus::Completed->value,
                SalesOrderStatus::Cancelled->value,
                SalesOrderStatus::Won->value,
            ], true);
    }

    private function owns(User $user, SalesOrder $salesOrder): bool
    {
        return $this->isSales($user)
            && $salesOrder->quotation()->where('sales_id', $user->id)->exists();
    }

    private function isSales(User $user): bool
    {
        return $user->role === 'sales' && $user->is_active;
    }
}
