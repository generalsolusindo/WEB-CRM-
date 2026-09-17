<?php

namespace App\Actions\Sales;

use App\Enums\QuotationStatus;
use App\Models\Notification;
use App\Models\Quotation;
use App\Models\Tax;
use App\Services\Sales\AgreedDpp;
use App\Services\Sales\LinePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateQuotation
{
    /** @param array<string, mixed> $data */
    public function handle(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $locked = Quotation::query()->with(['lines', 'lead.delegatedTo'])->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($locked->salesOrder()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Quotation yang sudah menjadi Sales Order tidak dapat diubah.']);
            }

            if ($locked->revisions()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Quotation ini sudah punya revisi yang lebih baru, tidak dapat diubah lagi.']);
            }

            $locked->update([
                // Angka berubah -> kembali ke Draft supaya wajib direview & dikirim ulang
                // ke customer, apa pun status sebelumnya (Sent/Rejected).
                'status' => QuotationStatus::Draft->value,
                // Dianggap penawaran baru sejak hari ini -> masa berlaku & tanggal dokumen
                // dihitung ulang, sama seperti saat quotation pertama kali dibuat (bukan
                // sisa masa berlaku / tanggal lama). quoted_at sengaja terpisah dari
                // updated_at karena updated_at ikut berubah oleh aksi lain yang bukan
                // edit isi (mis. review PM/Manager), jadi tidak bisa dipakai sebagai
                // "tanggal dokumen" yang tercetak.
                'valid_until' => now()->addDays(10)->toDateString(),
                'quoted_at' => now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'agreed_dpp' => isset($data['agreed_dpp']) && $data['agreed_dpp'] !== null && $data['agreed_dpp'] !== ''
                    ? (float) $data['agreed_dpp']
                    : null,
                'pm_review_status' => null,
                'pm_reviewed_by' => null,
                'pm_reviewed_at' => null,
                'pm_review_notes' => null,
                'manager_review_status' => null,
                'manager_reviewed_by' => null,
                'manager_reviewed_at' => null,
                'manager_review_notes' => null,
            ]);

            $taxRates = Tax::pluck('rate', 'id');

            // Snapshot baris lama SEBELUM dihapus, dikunci per procurement_request_line_id —
            // dipakai dua hal: (1) fallback nilai field yang tidak dikirim ulang oleh client
            // untuk baris lama (payload boleh parsial), (2) sumber cost_price yang sesungguhnya
            // untuk baris lama (tidak pernah dipercaya dari input client, supaya Sales tidak
            // bisa memalsukan margin pada item yang sudah divalidasi Procurement).
            $existingByPrLineId = $locked->lines->keyBy('procurement_request_line_id');

            // Ganti seluruh baris (bukan cuma update satu-satu yang harus selalu cocok dengan
            // set procurement_request_line_id semula) — supaya Sales bisa bebas menambah &
            // menghapus item saat edit, bukan cuma mengubah nilai item yang sudah ada. Pola ini
            // sama seperti UpdateInvoice.
            $locked->lines()->delete();

            foreach ($data['lines'] as $input) {
                $prLineId = isset($input['procurement_request_line_id']) ? (int) $input['procurement_request_line_id'] : null;
                $existing = $prLineId !== null ? $existingByPrLineId->get($prLineId) : null;

                $costPrice = $existing !== null
                    ? (float) $existing->cost_price
                    : round((float) ($input['cost_price'] ?? 0), 2);

                $qty = isset($input['qty']) && $input['qty'] !== '' ? (float) $input['qty'] : (float) ($existing->qty ?? 0);
                $itemName = ($input['item_name'] ?? '') !== '' ? $input['item_name'] : ($existing->item_name ?? '');
                $unit = ($input['unit'] ?? '') !== '' ? $input['unit'] : ($existing->unit ?? '');
                $category = in_array($input['category'] ?? null, ['material', 'service', 'reimburse'], true)
                    ? $input['category']
                    : ($existing->category ?? 'material');
                $description = array_key_exists('description', $input)
                    ? ($input['description'] ?: null)
                    : ($existing->description ?? null);
                $sourcingNote = array_key_exists('sourcing_note', $input)
                    ? ($input['sourcing_note'] ?: null)
                    : ($existing->sourcing_note ?? null);

                $taxId = array_key_exists('tax_id', $input) ? $input['tax_id'] : ($existing->tax_id ?? null);
                $taxRate = $input['tax_rate'] ?? ($taxId ? (float) ($taxRates[$taxId] ?? 0) : (float) ($existing->tax_rate ?? 0));

                $priced = LinePricing::resolve(
                    $qty,
                    (float) $input['selling_price'],
                    $costPrice,
                    isset($input['discount_percent']) ? (float) $input['discount_percent'] : null,
                    isset($input['discount_amount']) ? (float) $input['discount_amount'] : null,
                );

                $locked->lines()->create([
                    'procurement_request_line_id' => $prLineId,
                    'item_name' => $itemName,
                    'category' => $category,
                    'description' => $description,
                    'sourcing_note' => $sourcingNote,
                    'qty' => $qty,
                    'unit' => $unit,
                    'cost_price' => $costPrice,
                    'selling_price' => $input['selling_price'],
                    'discount_percent' => $priced['discount_percent'],
                    'discount_amount' => $priced['discount_amount'],
                    'markup_percent' => $priced['markup_percent'],
                    'tax_id' => $taxId,
                    'tax_rate' => round((float) $taxRate, 2),
                    'subtotal' => $priced['subtotal'],
                ]);
            }

            $locked->load('lines');

            if ($locked->agreed_dpp !== null) {
                AgreedDpp::distribute($locked->lines, (float) $locked->agreed_dpp);
            }

            $pm = $locked->lead?->delegatedTo;
            if ($pm) {
                Notification::updateOrCreate(
                    [
                        'user_id' => $pm->id,
                        'type' => 'quotation.pending_pm_review',
                        'related_type' => $locked->getMorphClass(),
                        'related_id' => $locked->id,
                    ],
                    [
                        'message' => "Quotation {$locked->number} diubah dan perlu diverifikasi ulang oleh Anda.",
                        'is_sent' => true,
                        'read_at' => null,
                    ],
                );
            }

            return $locked->refresh();
        });
    }
}
