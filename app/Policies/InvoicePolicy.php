<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isFinance($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user);
    }

    public function create(User $user): bool
    {
        return $this->isFinance($user);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->isFinance($user)
            && $invoice->status === InvoiceStatus::Draft->value;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        if (! $this->isFinance($user)) {
            return false;
        }

        // Invoice survey: single-line, tanpa logika project/settlement — Finance boleh void
        // walau ada pembayaran sebagian (refund uang ditangani manual di luar sistem).
        if ($invoice->isSurvey()) {
            return in_array($invoice->status, [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Sent->value,
                InvoiceStatus::PartiallyPaid->value,
            ], true);
        }

        return in_array($invoice->status, [
            InvoiceStatus::Draft->value,
            InvoiceStatus::Sent->value,
        ], true)
            && ! $invoice->payments()->exists();
    }

    private function isFinance(User $user): bool
    {
        return $user->role === 'finance' && $user->is_active;
    }
}
