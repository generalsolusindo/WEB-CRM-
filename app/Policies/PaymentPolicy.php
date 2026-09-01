<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class PaymentPolicy
{
    public function create(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && ! in_array($invoice->status, [
                InvoiceStatus::Cancelled->value,
                InvoiceStatus::Paid->value,
            ], true);
    }

    private function isFinance(User $user): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }
}
