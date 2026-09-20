<?php

namespace App\Actions\Procurement;

use App\Enums\AvailabilityStatus;
use App\Enums\LeadStage;
use App\Enums\ProcurementRequestStatus;
use App\Models\Notification;
use App\Models\ProcurementRequest;
use App\Services\Notifications\Notify;
use App\Services\Sales\LinePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarkProcurementRequestReady
{
    public function __construct(private Notify $notify) {}

    public function handle(ProcurementRequest $procurementRequest): ProcurementRequest
    {
        return DB::transaction(function () use ($procurementRequest) {
            $locked = ProcurementRequest::query()
                ->with(['lines.tax', 'lead.sales', 'lead.contact'])
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

            $quotation = $locked->quotations()->with('lines')->latest('id')->first();
            if ($quotation && ! $quotation->salesOrder()->exists()) {
                $requestLineIds = $locked->lines->pluck('id');
                $quotation->lines()->whereNotIn('procurement_request_line_id', $requestLineIds)->delete();
                $quotation->lines()->whereNull('procurement_request_line_id')->delete();

                foreach ($locked->lines as $requestLine) {
                    $line = $quotation->lines->firstWhere('procurement_request_line_id', $requestLine->id);
                    $sellingPrice = (float) ($line?->selling_price ?? 0);
                    $priced = LinePricing::resolve(
                        (float) $requestLine->qty,
                        $sellingPrice,
                        (float) $requestLine->cost_price,
                        $line?->discount_percent !== null ? (float) $line->discount_percent : null,
                        $line?->discount_amount !== null ? (float) $line->discount_amount : null,
                    );

                    $quotation->lines()->updateOrCreate(
                        ['procurement_request_line_id' => $requestLine->id],
                        [
                            'item_name' => $requestLine->item_name,
                            'category' => $requestLine->category,
                            'description' => $requestLine->description,
                            'sourcing_note' => $requestLine->sourcing_note,
                            'qty' => $requestLine->qty,
                            'unit' => $requestLine->unit,
                            'cost_price' => $requestLine->cost_price,
                            'selling_price' => $sellingPrice,
                            'discount_percent' => $priced['discount_percent'],
                            'discount_amount' => $priced['discount_amount'],
                            'markup_percent' => $priced['markup_percent'],
                            'tax_id' => $requestLine->tax_id,
                            'tax_rate' => (float) ($requestLine->tax?->rate ?? 0),
                            'subtotal' => $priced['subtotal'],
                        ],
                    );
                }

                $locked->lead->update(['stage' => LeadStage::Quotation->value]);
            }

            $sales = $locked->lead->sales;
            $customer = $locked->lead->contact?->name ?? 'customer';

            if ($sales) {
                $message = 'Procurement Request PR-'.str_pad((string) $locked->id, 6, '0', STR_PAD_LEFT)." ({$customer}) sudah Ready. ".($quotation ? 'Silakan lengkapi harga jual quotation.' : 'Silakan buat Quotation.');

                if ($quotation) {
                    Notification::updateOrCreate(
                        [
                            'user_id' => $sales->id,
                            'type' => 'procurement_request.ready',
                            'related_type' => $locked->getMorphClass(),
                            'related_id' => $locked->id,
                        ],
                        ['message' => $message, 'is_sent' => true, 'read_at' => null],
                    );
                } else {
                    $this->notify->once($sales, 'procurement_request.ready', $message, $locked);
                }
            }

            return $locked;
        });
    }
}
