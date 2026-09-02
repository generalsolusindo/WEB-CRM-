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

class CreateFinalInvoice
{
    public function __construct(private DocumentNumber $documentNumber) {}

    public function handle(SalesOrder $salesOrder, User $user, ?string $dueDate, ?float $pph23Rate = null): Invoice
    {
        return DB::transaction(function () use ($salesOrder, $user, $dueDate, $pph23Rate) {
            $order = SalesOrder::query()
                ->with('lines.tax')
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->order_type === OrderType::MaterialOnly->value) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Order Material Only sudah dibayar 100% di muka, tidak ada pelunasan.',
                ]);
            }

            $dpPaid = $order->invoices()
                ->where('invoice_phase', InvoicePhase::Dp->value)
                ->where('status', InvoiceStatus::Paid->value)
                ->exists();

            if (! $dpPaid) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Invoice DP harus lunas sebelum membuat pelunasan.',
                ]);
            }

            if (! $order->projects()->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Project belum Completed. Pelunasan baru bisa ditagih setelah project selesai.',
                ]);
            }

            if ($order->invoices()->where('invoice_phase', InvoicePhase::Final->value)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)->exists()) {
                throw ValidationException::withMessages([
                    'sales_order' => 'Invoice pelunasan sudah ada untuk Sales Order ini.',
                ]);
            }

            // Ambil rincian yang sudah ditagih di invoice DP — pelunasan menagih sisanya,
            // apa pun persentase DP yang dipakai Finance.
            $dpInvoice = $order->invoices()
                ->where('invoice_phase', InvoicePhase::Dp->value)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->with('lines')
                ->latest()
                ->first();

            $dpSubtotalByLine = [];
            $dpDiscountByLine = [];
            $dpCreditBilled = 0.0;
            foreach ($dpInvoice?->lines ?? [] as $line) {
                if ($line->sales_order_line_id === null) {
                    $dpCreditBilled += abs((float) $line->subtotal); // baris kredit survey (negatif)

                    continue;
                }
                $dpSubtotalByLine[$line->sales_order_line_id] = ($dpSubtotalByLine[$line->sales_order_line_id] ?? 0) + (float) $line->subtotal;
                $dpDiscountByLine[$line->sales_order_line_id] = ($dpDiscountByLine[$line->sales_order_line_id] ?? 0) + (float) $line->discount_amount;
            }

            $invoice = Invoice::create([
                'number' => $this->documentNumber->nextInvoiceNumber(),
                'sales_order_id' => $order->id,
                'invoice_phase' => InvoicePhase::Final->value,
                'status' => InvoiceStatus::Draft->value,
                'amount' => 0,
                'tax_amount' => 0,
                'due_date' => $dueDate,
                'created_by' => $user->id,
            ]);

            $amount = 0.0;
            $taxTotal = 0.0;

            foreach ($order->lines as $soLine) {
                $full = (float) $soLine->subtotal;
                $remaining = round($full - round((float) ($dpSubtotalByLine[$soLine->id] ?? 0), 2), 2);
                $discountFull = (float) $soLine->discount_amount;
                $discountRemaining = round($discountFull - round((float) ($dpDiscountByLine[$soLine->id] ?? 0), 2), 2);
                $qty = (float) $soLine->qty;
                $unitPrice = $qty > 0 ? round(($remaining + $discountRemaining) / $qty, 2) : 0.0;
                $taxAmount = round($remaining * (float) $soLine->tax_rate / 100, 2);

                $invoice->lines()->create([
                    'sales_order_line_id' => $soLine->id,
                    'item_name' => $soLine->item_name.' (Pelunasan)',
                    'category' => $soLine->category,
                    'qty' => $soLine->qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountRemaining,
                    'tax_id' => $soLine->tax_id,
                    'tax_rate' => $soLine->tax_rate,
                    'subtotal' => $remaining,
                ]);

                $amount += $remaining;
                $taxTotal += $taxAmount;
            }

            // Sisa kredit biaya survey (bagian pelunasan) — apa pun yang belum dipotong di DP.
            $creditFull = (float) $order->survey_credit;
            $creditRemaining = round($creditFull - round($dpCreditBilled, 2), 2);
            if ($creditRemaining > 0) {
                $invoice->lines()->create([
                    'sales_order_line_id' => null,
                    'item_name' => 'Kredit Biaya Survey (Pelunasan)',
                    'qty' => 1,
                    'unit_price' => -$creditRemaining,
                    'discount_amount' => 0,
                    'tax_id' => null,
                    'tax_rate' => 0,
                    'subtotal' => -$creditRemaining,
                ]);
                $amount -= $creditRemaining;
            }

            // PPh 23 (default 2%) atas TOTAL DPP jasa Sales Order — dipotong sekali di pelunasan.
            $serviceDpp = round((float) $order->lines->where('category', 'service')->sum('subtotal'), 2);
            $pph23FinalRate = $serviceDpp > 0 ? max(0.0, min(10.0, $pph23Rate ?? 2.0)) : 0.0;
            $pph23FinalAmount = round($serviceDpp * $pph23FinalRate / 100);

            $invoice->update([
                'amount' => round($amount, 2),
                'tax_amount' => round($taxTotal, 2),
                'pph23_rate' => $pph23FinalRate,
                'pph23_amount' => $pph23FinalAmount,
            ]);

            return $invoice->refresh();
        });
    }
}
