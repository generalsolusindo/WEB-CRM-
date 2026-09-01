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

    public function handle(SalesOrder $salesOrder, User $user, ?string $dueDate): Invoice
    {
        return DB::transaction(function () use ($salesOrder, $user, $dueDate) {
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
                $dp = round($full * 0.5, 2);
                $remaining = round($full - $dp, 2); // sisa tepat, tanpa drift pembulatan
                $discountFull = (float) $soLine->discount_amount;
                $discountRemaining = round($discountFull - round($discountFull * 0.5, 2), 2);
                $qty = (float) $soLine->qty;
                $unitPrice = $qty > 0 ? round(($remaining + $discountRemaining) / $qty, 2) : 0.0;
                $taxAmount = round($remaining * (float) $soLine->tax_rate / 100, 2);

                $invoice->lines()->create([
                    'sales_order_line_id' => $soLine->id,
                    'item_name' => $soLine->item_name.' (Pelunasan)',
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

            // Sisa kredit biaya survey (bagian pelunasan) — bayangan split 50/50 seperti DP.
            $creditFull = (float) $order->survey_credit;
            $creditRemaining = round($creditFull - round($creditFull * 0.5, 2), 2);
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

            $invoice->update([
                'amount' => round($amount, 2),
                'tax_amount' => round($taxTotal, 2),
            ]);

            return $invoice->refresh();
        });
    }
}
