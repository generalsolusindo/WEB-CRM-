<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementPaymentStatus;
use App\Models\ProcurementPayment;
use App\Models\User;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmProcurementPayment
{
    public function __construct(private Notify $notify) {}

    public function handle(ProcurementPayment $payment, User $procurement): ProcurementPayment
    {
        return DB::transaction(function () use ($payment, $procurement) {
            $locked = ProcurementPayment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== ProcurementPaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'procurement_payment' => 'Hanya pengajuan yang sudah dibayar Finance yang bisa dikonfirmasi.',
                ]);
            }

            $locked->update([
                'status' => ProcurementPaymentStatus::Confirmed->value,
                'confirmed_by' => $procurement->id,
                'confirmed_at' => now(),
            ]);

            $this->notify->resolve('procurement_payment.paid', $locked);

            return $locked->fresh();
        });
    }
}
