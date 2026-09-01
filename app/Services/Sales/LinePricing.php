<?php

namespace App\Services\Sales;

/**
 * Menghitung angka finansial satu baris quotation secara konsisten:
 *   Bruto = qty x selling_price
 *   Diskon (persen ATAU rupiah, saling terhubung)
 *   DPP   = Bruto - Diskon        (disimpan di kolom `subtotal`)
 *   PPN   = DPP x tax_rate%       (accessor `tax_amount`)
 */
class LinePricing
{
    /**
     * @return array{discount_percent: float|null, discount_amount: float, subtotal: float, markup_percent: float|null}
     */
    public static function resolve(
        float $qty,
        float $sellingPrice,
        float $costPrice,
        ?float $discountPercent,
        ?float $discountAmount,
    ): array {
        $gross = round($qty * $sellingPrice, 2);

        if ($discountPercent !== null && $discountPercent > 0) {
            $percent = min(max($discountPercent, 0), 100);
            $amount = round($gross * $percent / 100, 2);
        } elseif ($discountAmount !== null && $discountAmount > 0) {
            $amount = min(max($discountAmount, 0), $gross);
            $percent = $gross > 0 ? round($amount / $gross * 100, 2) : null;
        } else {
            $percent = null;
            $amount = 0.0;
        }

        $subtotal = round($gross - $amount, 2);

        return [
            'discount_percent' => $percent,
            'discount_amount' => $amount,
            'subtotal' => $subtotal,
            'markup_percent' => $costPrice > 0
                ? round((($sellingPrice - $costPrice) / $costPrice) * 100, 2)
                : null,
        ];
    }

    /**
     * Margin efektif setelah diskon: (DPP - total cost) / total cost x 100.
     */
    public static function effectiveMargin(float $qty, float $costPrice, float $subtotal): ?float
    {
        $totalCost = round($qty * $costPrice, 2);

        return $totalCost > 0
            ? round(($subtotal - $totalCost) / $totalCost * 100, 2)
            : null;
    }
}
