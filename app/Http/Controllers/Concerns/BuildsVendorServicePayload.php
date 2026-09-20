<?php

namespace App\Http\Controllers\Concerns;

use App\Models\VendorServicePayment;

trait BuildsVendorServicePayload
{
    /** @return array<string, mixed>|null */
    private function vendorServicePayload(?VendorServicePayment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'id' => $payment->id,
            'number' => $payment->number,
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'vendor_id' => $payment->vendor_id,
            'vendor_name' => $payment->vendor?->name,
            'total_fee' => $payment->total_fee,
            'terms' => $payment->terms,
            'dp_amount' => $payment->dp_amount,
            'dp_percent' => $payment->dpPercent(),
            'final_amount' => $payment->finalAmount(),
            'bank_name' => $payment->bank_name,
            'account_number' => $payment->account_number,
            'account_holder' => $payment->account_holder,
            'notes' => $payment->notes,
            'released_at' => $payment->released_at,
        ];
    }
}
