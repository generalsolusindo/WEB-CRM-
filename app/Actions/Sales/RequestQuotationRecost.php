<?php

namespace App\Actions\Sales;

use App\Enums\LeadStage;
use App\Enums\ProcurementRequestStatus;
use App\Enums\QuotationStatus;
use App\Models\Notification;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Notifications\Notify;
use App\Support\ProcurementScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestQuotationRecost
{
    public function __construct(private Notify $notify) {}

    /** @param array<int, array<string, mixed>> $lines */
    public function handle(Quotation $quotation, array $lines): Quotation
    {
        return DB::transaction(function () use ($quotation, $lines) {
            $locked = Quotation::query()
                ->with(['procurementRequest.lines', 'lead'])
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->procurementRequest->status !== ProcurementRequestStatus::Ready->value) {
                throw ValidationException::withMessages([
                    'quotation' => 'Procurement Request masih dalam proses costing ulang.',
                ]);
            }

            $request = $locked->procurementRequest;
            $existingById = $request->lines->keyBy('id');
            $keptIds = collect($lines)->pluck('procurement_request_line_id')->filter()->map(fn ($id) => (int) $id);

            // Hanya menghapus item (tidak ada item baru / berubah)? Tidak ada harga baru yang
            // dibutuhkan, jadi tidak perlu costing ulang oleh Procurement.
            $needsCosting = collect($lines)->contains(function ($line) use ($existingById) {
                $lineId = $line['procurement_request_line_id'] ?? null;
                $existing = $lineId ? $existingById->get((int) $lineId) : null;

                return $existing === null || ProcurementScope::differs($existing, [
                    'item_name' => $line['item_name'],
                    'description' => $line['description'] ?? null,
                    'qty' => $line['qty'],
                    'unit' => $line['unit'],
                    'category' => $line['category'],
                ]);
            });

            if (! $needsCosting) {
                if ($existingById->keys()->diff($keptIds)->isEmpty()) {
                    throw ValidationException::withMessages(['lines' => 'Tidak ada perubahan pada kebutuhan.']);
                }

                return $this->removeItemsOnly($locked, $keptIds);
            }

            foreach ($lines as $line) {
                $scope = [
                    'item_name' => $line['item_name'],
                    'description' => $line['description'] ?? null,
                    'qty' => $line['qty'],
                    'unit' => $line['unit'],
                    'category' => $line['category'],
                ];
                $resetSourcing = [
                    'vendor_product_id' => null,
                    'sourcing_note' => null,
                    'cost_price' => 0,
                    'tax_id' => null,
                    'availability_status' => 'searching',
                ];

                $lineId = $line['procurement_request_line_id'] ?? null;
                if ($lineId) {
                    $existing = $existingById->get((int) $lineId);
                    $attributes = ProcurementScope::differs($existing, $scope)
                        ? [...$scope, ...$resetSourcing]
                        : $scope;
                    $request->lines()->whereKey($lineId)->update($attributes);
                } else {
                    $keptIds->push($request->lines()->create([...$scope, ...$resetSourcing])->id);
                }
            }

            $keptIds->isEmpty()
                ? $request->lines()->delete()
                : $request->lines()->whereNotIn('id', $keptIds)->delete();
            $request->update([
                'status' => ProcurementRequestStatus::Submitted->value,
                'rejection_reason' => null,
            ]);

            $locked->update([
                'status' => QuotationStatus::Draft->value,
                'quoted_at' => null,
                'pm_review_status' => null,
                'pm_reviewed_by' => null,
                'pm_reviewed_at' => null,
                'pm_review_notes' => null,
                'manager_review_status' => null,
                'manager_reviewed_by' => null,
                'manager_reviewed_at' => null,
                'manager_review_notes' => null,
            ]);
            $locked->lead->update(['stage' => LeadStage::Procurement->value]);

            Notification::query()
                ->where('related_type', $locked->getMorphClass())
                ->where('related_id', $locked->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $this->notify->onceForEach(
                User::query()->where('role', 'procurement')->where('is_active', true)->get(),
                'procurement_request.recost_requested',
                "Revisi kebutuhan quotation {$locked->number} memerlukan costing ulang.",
                $request,
            );

            return $locked->refresh();
        });
    }

    /** Hapus item langsung: PR tetap Ready, baris quotation terkait ikut dihapus, review di-reset. */
    private function removeItemsOnly(Quotation $quotation, \Illuminate\Support\Collection $keptIds): Quotation
    {
        $quotation->procurementRequest->lines()->whereNotIn('id', $keptIds)->delete();
        $quotation->lines()->where(fn ($q) => $q->whereNull('procurement_request_line_id')->orWhereNotIn('procurement_request_line_id', $keptIds))->delete();

        $priced = $quotation->quoted_at !== null;
        $quotation->update([
            'status' => QuotationStatus::Draft->value,
            'quoted_at' => $priced ? now()->toDateString() : null,
            'valid_until' => $priced ? now()->addDays(10)->toDateString() : $quotation->valid_until,
            'pm_review_status' => null,
            'pm_reviewed_by' => null,
            'pm_reviewed_at' => null,
            'pm_review_notes' => null,
            'manager_review_status' => null,
            'manager_reviewed_by' => null,
            'manager_reviewed_at' => null,
            'manager_review_notes' => null,
        ]);

        Notification::query()
            ->where('related_type', $quotation->getMorphClass())
            ->where('related_id', $quotation->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $quotation->refresh();
    }
}
