<?php

namespace App\Services\Sales;

use Illuminate\Support\Collection;

/**
 * Membagi rata "Nilai DPP disepakati" (harga nett) ke seluruh baris dokumen
 * secara proporsional terhadap bruto (qty x selling_price) tiap baris, lalu
 * menyimpan diskon hasil hitung mundur ke masing-masing baris. Menimpa diskon
 * per-baris yang sudah ada.
 */
class AgreedDpp
{
    /**
     * @param  Collection<int, \App\Models\QuotationLine|\App\Models\SalesOrderLine>  $lines
     */
    public static function distribute(Collection $lines, float $agreedDpp): void
    {
        $gross = round($lines->sum(fn ($l) => (float) $l->qty * (float) $l->selling_price), 2);

        if ($gross <= 0) {
            return;
        }

        // Tidak menaikkan harga di atas bruto — target dibatasi maksimal = gross.
        $target = max(0.0, min($agreedDpp, $gross));
        $factor = $target / $gross;

        $running = 0.0;
        $last = $lines->count() - 1;

        $lines->values()->each(function ($line, $i) use ($factor, $target, &$running, $last) {
            $lineGross = round((float) $line->qty * (float) $line->selling_price, 2);

            if ($i === $last) {
                $subtotal = round($target - $running, 2);
            } else {
                $subtotal = round($lineGross * $factor, 2);
                $running = round($running + $subtotal, 2);
            }

            $discount = round($lineGross - $subtotal, 2);

            $line->update([
                'discount_amount' => max($discount, 0),
                'discount_percent' => $lineGross > 0 ? round($discount / $lineGross * 100, 2) : null,
                'subtotal' => $subtotal,
            ]);
        });
    }
}
