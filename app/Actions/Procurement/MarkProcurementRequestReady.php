<?php

namespace App\Actions\Procurement;

use App\Enums\AvailabilityStatus;
use App\Enums\ProcurementRequestStatus;
use App\Models\ProcurementRequest;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkProcurementRequestReady
{
    public function __construct(private Notify $notify) {}

    public function handle(ProcurementRequest $procurementRequest): ProcurementRequest
    {
        return DB::transaction(function () use ($procurementRequest) {
            $locked = ProcurementRequest::query()
                ->with(['lines', 'lead.sales', 'lead.contact'])
                ->whereKey($procurementRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [
                ProcurementRequestStatus::Submitted->value,
                ProcurementRequestStatus::Searching->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'procurement_request' => 'Hanya PR yang sedang diproses yang bisa ditandai Ready.',
                ]);
            }

            if ($locked->lines->contains(fn ($line) => $line->availability_status !== AvailabilityStatus::Available->value)) {
                throw ValidationException::withMessages([
                    'lines' => 'Semua item harus berstatus Tersedia sebelum PR ditandai Ready.',
                ]);
            }

            if ($locked->lines->contains(fn ($line) => (float) $line->cost_price <= 0)) {
                throw ValidationException::withMessages([
                    'lines' => 'Setiap item harus memiliki cost price lebih dari 0.',
                ]);
            }

            $locked->update(['status' => ProcurementRequestStatus::Ready->value]);

            $sales = $locked->lead->sales;
            $customer = $locked->lead->contact?->name ?? 'customer';

            if ($sales) {
                $this->notify->once(
                    $sales,
                    'procurement_request.ready',
                    "Procurement Request PR-".str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT)." ({$customer}) sudah Ready. Silakan buat Quotation.",
                    $locked,
                );
            }

            return $locked;
        });
    }
}
