<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateInvoice
{
    /** @param array<int, array<string, mixed>> $lines */
    public function handle(Invoice $invoice, ?string $dueDate, ?string $notes, array $lines): Invoice
    {
        return DB::transaction(function () use ($invoice, $dueDate, $notes, $lines) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->payments()->exists()) {
                throw ValidationException::withMessages(['invoice' => 'Invoice yang sudah ada pembayarannya tidak dapat diubah.']);
            }

            if ($locked->status === InvoiceStatus::Cancelled->value) {
                throw ValidationException::withMessages(['invoice' => 'Invoice yang dibatalkan tidak dapat diubah.']);
            }

            $locked->lines()->delete();

            $amount = 0.0;
            $taxTotal = 0.0;

            foreach ($lines as $line) {
                $qty = (float) $line['qty'];
                $unitPrice = (float) $line['unit_price'];
                $discountAmount = round((float) ($line['discount_amount'] ?? 0), 2);
                $taxRate = round((float) ($line['tax_rate'] ?? 0), 2);
                $subtotal = round($qty * $unitPrice - $discountAmount, 2);
                $taxAmount = round($subtotal * $taxRate / 100, 2);

                $locked->lines()->create([
                    'sales_order_line_id' => $line['sales_order_line_id'] ?? null,
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

            // PPh 23 dihitung ulang dari baris jasa yang baru, tapi rate/enabled tidak
            // diubah di sini — itu punya alur editnya sendiri (managePph23).
            $pph23Amount = 0.0;
            if ($locked->pph23_enabled) {
                $serviceDpp = round((float) $locked->lines()->where('category', 'service')->sum('subtotal'), 2);
                $pph23Amount = $serviceDpp > 0 ? round($serviceDpp * (float) $locked->pph23_rate / 100) : 0.0;
            }

            $update = [
                'due_date' => $dueDate,
                'notes' => $notes,
                'amount' => round($amount, 2),
                'tax_amount' => round($taxTotal, 2),
                'pph23_amount' => $pph23Amount,
                // Angka berubah -> kembali ke Draft, invoice dianggap belum terkirim
                // lagi ke customer dan perlu dikirim ulang.
                'status' => InvoiceStatus::Draft->value,
            ];

            // Bukti potong PPh 23 yang sudah direkam jadi tidak sinkron kalau
            // nominalnya berubah gara-gara baris diedit — reset, minta dicatat ulang.
            if (round((float) $locked->pph23_amount, 2) !== round($pph23Amount, 2) && $locked->pph23_bukti_potong_no !== null) {
                $update['pph23_bukti_potong_no'] = null;
                $update['pph23_recorded_at'] = null;
                $update['pph23_recorded_by'] = null;
            }

            $locked->update($update);

            return $locked->refresh();
        });
    }
}
