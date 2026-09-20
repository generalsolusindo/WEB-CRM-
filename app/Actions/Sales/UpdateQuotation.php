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

            // Struktur kebutuhan dan cost selalu berasal dari Procurement. Editor quotation
            // hanya menangani nilai komersial seperti selling price dan diskon.
            $existingByPrLineId = $locked->lines->keyBy('procurement_request_line_id');
            $locked->lines()->delete();

            foreach ($data['lines'] as $input) {
                $prLineId = (int) $input['procurement_request_line_id'];
                $existing = $existingByPrLineId->get($prLineId);
                $costPrice = (float) $existing->cost_price;
                $qty = (float) $existing->qty;

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
                    'item_name' => $existing->item_name,
                    'category' => $existing->category,
                    'description' => $existing->description,
                    'sourcing_note' => $existing->sourcing_note,
                    'qty' => $qty,
                    'unit' => $existing->unit,
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
