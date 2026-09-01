<?php

namespace App\Services\Sales;

use Illuminate\Support\Collection;

/**
 * Ringkasan angka dokumen (quotation / sales order / invoice) dari kumpulan line.
 * Line diharapkan memiliki: subtotal (DPP), discount_amount, tax_amount, dan
 * (opsional) qty + cost_price untuk hitung margin.
 */
class DocumentTotals
{
    /**
     * @param  Collection<int, mixed>  $lines
     * @param  float  $surveyCredit  Potongan kredit biaya survey di level dokumen (0 bila sudah jadi baris).
     * @return array<string, float|null>
     */
    public static function of(Collection $lines, float $surveyCredit = 0.0): array
    {
        $dpp = round($lines->sum(fn ($l) => (float) $l->subtotal), 2);
        $discount = round($lines->sum(fn ($l) => (float) ($l->discount_amount ?? 0)), 2);
        $tax = round($lines->sum(fn ($l) => (float) $l->tax_amount), 2);
        $gross = round($dpp + $discount, 2);
        $cost = round($lines->sum(fn ($l) => (float) ($l->qty ?? 0) * (float) ($l->cost_price ?? 0)), 2);
        $credit = round(max($surveyCredit, 0), 2);

        return [
            'gross' => $gross,
            'discount' => $discount,
            'discount_percent' => $gross > 0 ? round($discount / $gross * 100, 2) : 0.0,
            'subtotal' => $dpp,
            'tax' => $tax,
            'survey_credit' => $credit,
            'grand_total' => round($dpp + $tax - $credit, 2),
            'margin_amount' => $cost > 0 ? round($dpp - $cost, 2) : null,
            'margin_percent' => $cost > 0 ? round(($dpp - $cost) / $cost * 100, 2) : null,
        ];
    }
}
