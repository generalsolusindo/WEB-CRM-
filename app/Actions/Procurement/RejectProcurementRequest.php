<?php

namespace App\Actions\Procurement;

use App\Enums\LeadStage;
use App\Enums\ProcurementRequestStatus;
use App\Models\ProcurementRequest;
use App\Models\Requirement;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectProcurementRequest
{
    public function __construct(private Notify $notify) {}

    public function handle(ProcurementRequest $procurementRequest, string $reason): ProcurementRequest
    {
        return DB::transaction(function () use ($procurementRequest, $reason) {
            $locked = ProcurementRequest::query()
                ->with(['lead.sales', 'lead.contact'])
                ->whereKey($procurementRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [
                ProcurementRequestStatus::Submitted->value,
                ProcurementRequestStatus::Searching->value,
            ], true)) {
                throw ValidationException::withMessages([
                    'procurement_request' => 'PR ini tidak dapat ditolak pada statusnya sekarang.',
                ]);
            }

            $locked->update([
                'status' => ProcurementRequestStatus::Rejected->value,
                'rejection_reason' => $reason,
            ]);

            // Buka lagi requirement yang ikut PR ini supaya Sales bisa revisi & submit ulang.
            Requirement::whereIn('id', $locked->lines()->pluck('requirement_id'))
                ->update(['submitted_at' => null]);

            if ($locked->lead->stage === LeadStage::Procurement->value) {
                $locked->lead->update(['stage' => LeadStage::Requirement->value]);
            }

            $sales = $locked->lead->sales;
            $customer = $locked->lead->contact?->name ?? 'customer';

            if ($sales) {
                $this->notify->once(
                    $sales,
                    'procurement_request.rejected',
                    "Procurement Request PR-".str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT)." ({$customer}) ditolak: {$reason}",
                    $locked,
                );
            }

            return $locked;
        });
    }
}
