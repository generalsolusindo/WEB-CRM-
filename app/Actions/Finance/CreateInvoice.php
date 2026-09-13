<?php

namespace App\Actions\Finance;

use App\Enums\InvoicePhase;
use App\Enums\InvoiceStatus;
use App\Enums\OrderType;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\DocumentNumber;
use App\Services\Notifications\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoice
{
    public function __construct(private DocumentNumber $documentNumber, private Notify $notify) {}

    /**
     * @param  float|null  $dpPercent  Persentase DP (default 50) — hanya dipakai saat $phase = DP.
     * @param  float|null  $agreedDpp  Finalisasi nilai DPP oleh Finance — menimpa diskon Sales Order (satu deal, 100%).
     * @param  float|null  $ppnRate  Override tarif PPN untuk seluruh baris Sales Order (0/11/12).
     * @param  bool  $pph23Enabled  Customer memotong PPh 23 atas baris jasa.
     * @param  array<int, array{sales_order_line_id?: int|null, item_name: string, category: string, qty: float, unit_price: float, discount_amount?: float|null, tax_rate?: float|null}>|null  $lines
     *         Baris invoice yang di-edit/ditambah/dihapus manual oleh Finance — kalau diisi, menggantikan
     *         perhitungan otomatis dari baris Sales Order (dan mengabaikan $agreedDpp/$ppnRate). Hanya
     *         berlaku untuk invoice ini, tidak menimpa data Sales Order/Quotation aslinya.
     * @param  string|null  $notes  Catatan Finance menjelaskan alasan perubahan (opsional).
     */
    public function handle(
        SalesOrder $salesOrder,
        User $user,
        InvoicePhase $phase,
        ?string $dueDate,
        ?float $dpPercent = null,
        ?float $pph23Rate = null,
        ?float $agreedDpp = null,
        ?float $ppnRate = null,
        bool $pph23Enabled = false,
        ?array $lines = null,
        ?string $notes = null,
    ): Invoice {
        return DB::transaction(function () use ($salesOrder, $user, $phase, $dueDate, $dpPercent, $pph23Rate, $agreedDpp, $ppnRate, $pph23Enabled, $lines, $notes) {
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

            $hasManualLines = $lines !== null && $lines !== [];

            // Finance memfinalisasi nilai deal & PPN Sales Order sebelum invoice muka
            // pertama terbit. Disimpan di Sales Order supaya pelunasan ikut konsisten.
            // Tidak berlaku kalau Finance sudah mengedit baris invoice secara manual —
            // baris manual sudah final apa adanya, tidak perlu difinalisasi ulang.
            if (! $hasManualLines && $agreedDpp !== null) {
                \App\Services\Sales\AgreedDpp::distribute($order->lines, $agreedDpp);
                $order->update(['agreed_dpp' => $agreedDpp]);
                $order->load('lines.tax');
            }

            if (! $hasManualLines && $ppnRate !== null) {
                foreach ($order->lines as $soLine) {
                    $soLine->update(['tax_id' => null, 'tax_rate' => round($ppnRate, 2)]);
                }
                $order->load('lines.tax');
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
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            $amount = 0.0;
            $taxTotal = 0.0;

            if ($hasManualLines) {
                $validSoLineIds = $order->lines->pluck('id')->all();

                foreach ($lines as $line) {
                    $qty = (float) $line['qty'];
                    $unitPrice = (float) $line['unit_price'];
                    $discountAmount = round((float) ($line['discount_amount'] ?? 0), 2);
                    $taxRate = round((float) ($line['tax_rate'] ?? 0), 2);
                    $subtotal = round($qty * $unitPrice - $discountAmount, 2);
                    $taxAmount = round($subtotal * $taxRate / 100, 2);
                    $soLineId = $line['sales_order_line_id'] ?? null;

                    $invoice->lines()->create([
                        'sales_order_line_id' => in_array($soLineId, $validSoLineIds, true) ? $soLineId : null,
                        'item_name' => $line['item_name'],
                        'category' => $line['category'],
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'discount_amount' => $discountAmount,
                        'tax_id' => null,
                        'tax_rate' => $taxRate,
                        'subtotal' => $subtotal,
                    ]);

                    $amount += $subtotal;
                    $taxTotal += $taxAmount;
                }
            } else {
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
            }

            // PPh 23 dipotong customer bila ditandai — basis = baris JASA pada invoice
            // ini (sudah porsi DP / penuh), sehingga potongannya proporsional.
            $pph23FinalRate = 0.0;
            $pph23FinalAmount = 0.0;
            if ($pph23Enabled) {
                $serviceDpp = round((float) $invoice->lines()->where('category', 'service')->sum('subtotal'), 2);
                if ($serviceDpp > 0) {
                    $pph23FinalRate = max(0.0, min(10.0, $pph23Rate ?? 2.0));
                    $pph23FinalAmount = round($serviceDpp * $pph23FinalRate / 100);
                }
            }

            $invoice->update([
                'amount' => round($amount, 2),
                'tax_amount' => round($taxTotal, 2),
                'pph23_enabled' => $pph23Enabled,
                'pph23_rate' => $pph23FinalRate,
                'pph23_amount' => $pph23FinalAmount,
            ]);

            if ($phase === InvoicePhase::Dp) {
                $order->update(['dp_percent' => $percent]);
            }

            $this->notify->resolve('sales_order.created', $order);

            return $invoice->refresh();
        });
    }
}
