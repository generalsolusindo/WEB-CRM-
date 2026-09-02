<?php

namespace App\Actions\Finance;

use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoice
{
    public function __construct(private DocumentNumber $documentNumber) {}

    /**
     * @param  float|null  $dpPercent  Persentase DP (default 50) — hanya dipakai saat $phase = DP.
     */
    public function handle(SalesOrder $salesOrder, User $user, InvoicePhase $phase, ?string $dueDate, ?float $dpPercent = null, ?float $pph23Rate = null): Invoice
    {
        return DB::transaction(function () use ($salesOrder, $user, $phase, $dueDate, $dpPercent, $pph23Rate) {
            $order = SalesOrder::query()
                ->with('lines.tax')
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $allowedPhase = $order->order_type === OrderType::MaterialOnly->value
                ? InvoicePhase::Full
                : InvoicePhase::Dp;

            if ($phase !== $allowedPhase) {
                throw ValidationException::withMessages([
                    'phase' => "Order type ini hanya boleh invoice muka berjenis {$allowedPhase->label()}.",
                ]);
            }

            $hasUpfrontInvoice = $order->invoices()
                ->whereIn('invoice_phase', [InvoicePhase::Dp->value, InvoicePhase::Full->value])
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->exists();

            if ($hasUpfrontInvoice) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Sales Order ini sudah memiliki invoice muka aktif.',
                ]);
            }

            if ($order->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Sales Order tidak memiliki line item.',
                ]);
            }

            $percent = $phase === InvoicePhase::Dp
                ? max(1.0, min(99.0, $dpPercent ?? 50.0))
                : 100.0;
            $ratio = $percent / 100;
            $percentLabel = rtrim(rtrim(number_format($percent, 2), '0'), '.');
            $suffix = $phase === InvoicePhase::Dp ? " (DP {$percentLabel}%)" : '';

            $invoice = Invoice::create([
                'number' => $this->documentNumber->nextInvoiceNumber(),
                'sales_order_id' => $order->id,
                'invoice_phase' => $phase->value,
                'status' => InvoiceStatus::Draft->value,
                'amount' => 0,
                'tax_amount' => 0,
                'due_date' => $dueDate,
                'created_by' => $user->id,
            ]);

            $amount = 0.0;
            $taxTotal = 0.0;

            foreach ($order->lines as $soLine) {
                $subtotal = round((float) $soLine->subtotal * $ratio, 2);
                $discountAmount = round((float) $soLine->discount_amount * $ratio, 2);
                $qty = (float) $soLine->qty;
                $unitPrice = $qty > 0 ? round(($subtotal + $discountAmount) / $qty, 2) : 0.0;
                $taxAmount = round($subtotal * (float) $soLine->tax_rate / 100, 2);

                $invoice->lines()->create([
                    'sales_order_line_id' => $soLine->id,
                    'item_name' => $soLine->item_name.$suffix,
                    'category' => $soLine->category,
                    'qty' => $soLine->qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'tax_id' => $soLine->tax_id,
                    'tax_rate' => $soLine->tax_rate,
                    'subtotal' => $subtotal,
                ]);

                $amount += $subtotal;
                $taxTotal += $taxAmount;
            }

            // Kredit biaya survey yang sudah dibayar customer — dipotong proporsional.
            $creditPortion = round((float) $order->survey_credit * $ratio, 2);
            if ($creditPortion > 0) {
                $invoice->lines()->create([
                    'sales_order_line_id' => null,
                    'item_name' => 'Kredit Biaya Survey',
                    'qty' => 1,
                    'unit_price' => -$creditPortion,
                    'discount_amount' => 0,
                    'tax_id' => null,
                    'tax_rate' => 0,
                    'subtotal' => -$creditPortion,
                ]);
                $amount -= $creditPortion;
            }

            // PPh 23 dipotong sekali — hanya di invoice pembayaran penuh (bukan DP).
            $pph23FinalRate = 0.0;
            $pph23FinalAmount = 0.0;
            if ($phase === InvoicePhase::Full) {
                $serviceDpp = round((float) $order->lines->where('category', 'service')->sum('subtotal'), 2);
                if ($serviceDpp > 0) {
                    $pph23FinalRate = max(0.0, min(10.0, $pph23Rate ?? 2.0));
                    $pph23FinalAmount = round($serviceDpp * $pph23FinalRate / 100);
                }
            }

            $invoice->update([
                'amount' => round($amount, 2),
                'tax_amount' => round($taxTotal, 2),
                'pph23_rate' => $pph23FinalRate,
                'pph23_amount' => $pph23FinalAmount,
            ]);

            if ($phase === InvoicePhase::Dp) {
                $order->update(['dp_percent' => $percent]);
            }

            return $invoice->refresh();
        });
    }
}
