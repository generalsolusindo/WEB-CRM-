<?php

namespace App\Contracts;

interface ConversionSheetWriter
{
    /**
     * Tambahkan satu baris ke sheet Google Ads Conversion Tracker.
     *
     * @param  array{0: string, 1: string, 2: string, 3: float, 4: string}  $row  Urutan kolom: Phone Number, Conversion Name, Conversion Time, Conversion Value, Conversion Currency.
     */
    public function appendRow(array $row): void;
}
