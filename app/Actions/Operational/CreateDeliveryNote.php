<?php

namespace App\Actions\Operational;

use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLine;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDeliveryNote
{
    public function __construct(private DocumentNumber $documentNumber) {}

    /**
     * @param  array<int, array{sales_order_line_id: int, qty_delivered: float|string}>  $lines
     */
    public function handle(
        SalesOrder $salesOrder,
        User $user,
        array $lines,
        string $deliveryMethod,
        string $deliveryAddress,
        ?string $shipperName,
        ?string $trackingNumber,
        ?string $approvedByName,
        UploadedFile $dispatchProof,
    ): DeliveryNote {
        return DB::transaction(function () use (
            $salesOrder, $user, $lines, $deliveryMethod, $deliveryAddress,
            $shipperName, $trackingNumber, $approvedByName, $dispatchProof,
        ) {
            $order = SalesOrder::query()
                ->with('lines')
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $materialLines = $order->lines->where('category', 'material')->keyBy('id');

            if ($materialLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Sales Order ini tidak punya baris material.',
                ]);
            }

            $submitted = collect($lines)->keyBy('sales_order_line_id');
            if ($submitted->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Pilih minimal satu baris material untuk dikirim.',
                ]);
            }

            // Sudah terkirim di delivery note-delivery note sebelumnya, per baris SO.
            $alreadyDelivered = DeliveryNoteLine::query()
                ->whereIn('sales_order_line_id', $materialLines->keys())
                ->selectRaw('sales_order_line_id, SUM(qty_delivered) as total')
                ->groupBy('sales_order_line_id')
                ->pluck('total', 'sales_order_line_id');

            $invoice = $order->invoices()
                ->whereIn('invoice_phase', [InvoicePhase::Dp->value, InvoicePhase::Full->value])
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->latest()
                ->first();

            $deliveryNote = DeliveryNote::create([
                'number' => $this->documentNumber->nextDeliveryNoteNumber(),
                'sales_order_id' => $order->id,
                'invoice_id' => $invoice?->id,
                'delivery_method' => $deliveryMethod,
                'delivery_address' => $deliveryAddress,
                'shipper_name' => $shipperName,
                'tracking_number' => $trackingNumber,
                'approved_by_name' => $approvedByName,
                'status' => 'sent',
                'created_by' => $user->id,
            ]);

            $deliveryNote->attachments()->create([
                'category' => 'delivery_dispatch_proof',
                'file_path' => $dispatchProof->store('delivery-proofs'),
                'uploaded_by' => $user->id,
            ]);

            foreach ($submitted as $soLineId => $input) {
                $soLine = $materialLines->get($soLineId);
                if (! $soLine) {
                    throw ValidationException::withMessages([
                        'lines' => 'Baris yang dipilih bukan baris material pada Sales Order ini.',
                    ]);
                }

                $previousBalance = round((float) $soLine->qty - (float) ($alreadyDelivered[$soLineId] ?? 0), 2);
                $qtyDelivered = round((float) $input['qty_delivered'], 2);

                if ($qtyDelivered <= 0 || $qtyDelivered > $previousBalance) {
                    throw ValidationException::withMessages([
                        'lines' => "Qty kirim untuk \"{$soLine->item_name}\" harus antara 0 dan sisa {$previousBalance}.",
                    ]);
                }

                DeliveryNoteLine::create([
                    'delivery_note_id' => $deliveryNote->id,
                    'sales_order_line_id' => $soLine->id,
                    'item_name' => $soLine->item_name,
                    'unit' => $soLine->unit,
                    'qty_ordered' => $soLine->qty,
                    'qty_previous_balance' => $previousBalance,
                    'qty_delivered' => $qtyDelivered,
                    'qty_balance' => round($previousBalance - $qtyDelivered, 2),
                ]);
            }

            return $deliveryNote->load('lines');
        });
    }
}
