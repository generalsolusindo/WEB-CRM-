<?php

namespace App\Support;

/**
 * Syarat & ketentuan default yang tampil di cetakan Quotation — bisa diedit bebas
 * oleh Sales per quotation lewat field "Syarat & Ketentuan" di form.
 */
class QuotationDefaults
{
    public static function terms(): string
    {
        return <<<'TEXT'
        Price Include Tax
        Payment DP 50%
        Payment 50% After BAST
        Warranty Services 1 Month
        No Cancellation
        The final report will be submitted one business day after full payment (100%) has been received
        TEXT;
    }
}
