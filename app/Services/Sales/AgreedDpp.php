<?php

namespace App\Services\Sales;

use App\Models\QuotationLine;
use App\Models\SalesOrderLine;
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
     * @param  Collection<int, QuotationLine|SalesOrderLine>  $lines
     *
     * Semua angka rupiah bulat: nilai disepakati dibulatkan, tiap baris dibulatkan, dan sisa
     * pembulatan dibebankan ke baris terakhir supaya jumlah DPP persis sama dengan nilai itu.
     */
    public static function distribute(Collection $lines, float $agreedDpp): void
    {
        $gross = round($lines->sum(fn ($l) => round((float) $l->qty * (float) $l->selling_price)));

        if ($gross <= 0) {
            return;
        }

        // Tidak menaikkan harga di atas bruto — target dibatasi maksimal = gross.
        $target = max(0.0, min(round($agreedDpp), $gross));
        $factor = $target / $gross;

        $running = 0.0;
        $last = $lines->count() - 1;

        $lines->values()->each(function ($line, $i) use ($factor, $target, &$running, $last) {
            $lineGross = round((float) $line->qty * (float) $line->selling_price);

            if ($i === $last) {
                $subtotal = round($target - $running);
            } else {
                $subtotal = round($lineGross * $factor);
                $running = round($running + $subtotal);
            }

            $discount = round($lineGross - $subtotal);

            $line->update([
                'discount_amount' => max($discount, 0),
                'discount_percent' => $lineGross > 0 ? round($discount / $lineGross * 100, 2) : null,
                'subtotal' => $subtotal,
            ]);
        });
    }
}
