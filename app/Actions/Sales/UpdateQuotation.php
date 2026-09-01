<?php

namespace App\Actions\Sales;

use App\Models\Quotation;
use App\Models\Tax;
use App\Services\Sales\LinePricing;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateQuotation
{
    /** @param array<string, mixed> $data */
    public function handle(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $locked = Quotation::query()->with('lines')->whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['quotation' => 'Hanya quotation Draft yang dapat diubah.']);
            }

            $byId = collect($data['lines'])->keyBy(fn (array $line) => (int) $line['procurement_request_line_id']);
            $sourceIds = $locked->lines->pluck('procurement_request_line_id')->sort()->values();

            if ($sourceIds->all() !== $byId->keys()->sort()->values()->all()) {
                throw ValidationException::withMessages(['lines' => 'Line quotation tidak valid.']);
            }

            $locked->update([
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $taxRates = Tax::pluck('rate', 'id');

            foreach ($locked->lines as $line) {
                $input = $byId->get($line->procurement_request_line_id);

                $taxId = array_key_exists('tax_id', $input) ? $input['tax_id'] : $line->tax_id;
                $taxRate = $input['tax_rate'] ?? ($taxId ? (float) ($taxRates[$taxId] ?? 0) : 0.0);

                $priced = LinePricing::resolve(
                    (float) $line->qty,
                    (float) $input['selling_price'],
                    (float) $line->cost_price,
                    isset($input['discount_percent']) ? (float) $input['discount_percent'] : null,
                    isset($input['discount_amount']) ? (float) $input['discount_amount'] : null,
                );

                $line->update([
                    'selling_price' => $input['selling_price'],
                    'discount_percent' => $priced['discount_percent'],
                    'discount_amount' => $priced['discount_amount'],
                    'markup_percent' => $priced['markup_percent'],
                    'tax_id' => $taxId,
                    'tax_rate' => round((float) $taxRate, 2),
                    'subtotal' => $priced['subtotal'],
                ]);
            }

            return $locked->refresh();
        });
    }
}
