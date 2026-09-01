<?php

namespace App\Actions\Sales;

use App\Enums\LeadStage;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use App\Services\Sales\SalesOrderSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseSalesOrderAsWon
{
    public function __construct(private SalesOrderSettlement $settlement) {}

    public function handle(SalesOrder $salesOrder): void
    {
        DB::transaction(function () use ($salesOrder) {
            $locked = SalesOrder::query()
                ->with('quotation.lead')
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->settlement->canCloseAsWon($locked)) {
                throw ValidationException::withMessages([
                    'payment' => 'Transaksi belum dapat ditutup. Invoice pelunasan harus Paid dan memiliki bukti bayar.',
                ]);
            }

            $locked->update(['status' => SalesOrderStatus::Won->value]);
            $locked->quotation->lead->update(['stage' => LeadStage::Won->value]);
        });
    }
}
