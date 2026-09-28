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
     * @return array<string, float|null>
     */
    public static function of(Collection $lines): array
    {
        // Semua angka rupiah dibulatkan ke rupiah bulat (tanpa sen); hanya persentase yang
        // tetap berdesimal.
        $dpp = round($lines->sum(fn ($l) => (float) $l->subtotal));
        $discount = round($lines->sum(fn ($l) => (float) ($l->discount_amount ?? 0)));
        $tax = round($lines->sum(fn ($l) => (float) $l->tax_amount));
        $gross = round($dpp + $discount);
        $cost = round($lines->sum(fn ($l) => (float) ($l->qty ?? 0) * (float) ($l->cost_price ?? 0)));

        // DPP baris jasa — dasar estimasi PPh 23 (potongan final ditetapkan Finance saat invoice).
        $serviceDpp = round($lines->filter(fn ($l) => ($l->category ?? null) === 'service')->sum(fn ($l) => (float) $l->subtotal));

        return [
            'gross' => $gross,
            'discount' => $discount,
            'discount_percent' => $gross > 0 ? round($discount / $gross * 100, 2) : 0.0,
            'subtotal' => $dpp,
            'tax' => $tax,
            'service_dpp' => $serviceDpp,
            'pph23_estimate' => round($serviceDpp * 0.02),
            'grand_total' => round($dpp + $tax),
            'margin_amount' => $cost > 0 ? round($dpp - $cost) : null,
            'margin_percent' => $cost > 0 ? round(($dpp - $cost) / $cost * 100, 2) : null,
        ];
    }
}
