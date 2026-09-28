<?php

namespace Tests\Unit\Sales;

use App\Services\Sales\LinePricing;
use PHPUnit\Framework\TestCase;

class LinePricingTest extends TestCase
{
    public function test_extreme_markup_is_capped_to_the_column_limit_instead_of_overflowing(): void
    {
        $priced = LinePricing::resolve(200, 3220000, 10000, null, null);

        $this->assertSame(9999.99, $priced['markup_percent']);
        $this->assertSame(644000000.0, $priced['subtotal']);
    }

    public function test_normal_markup_is_untouched(): void
    {
        $priced = LinePricing::resolve(2, 1300000, 1000000, null, null);

        $this->assertSame(30.0, $priced['markup_percent']);
    }

    public function test_all_rupiah_amounts_are_rounded_to_whole_numbers(): void
    {
        // bruto 2,5 x 1.000.001 = 2.500.002,5 -> 2.500.003 ; diskon 10% = 250.000,3 -> 250.000
        $priced = LinePricing::resolve(2.5, 1000000.6, 500000, 10, null);

        $this->assertSame(1000001.0, $priced['selling_price']);
        $this->assertSame(250000.0, $priced['discount_amount']);
        $this->assertSame(2250003.0, $priced['subtotal']);
    }

    public function test_rupiah_discount_is_rounded_and_percent_is_back_calculated(): void
    {
        $priced = LinePricing::resolve(3, 33333, 10000, null, 1000.4);

        $this->assertSame(1000.0, $priced['discount_amount']);
        $this->assertSame(98999.0, $priced['subtotal']);
        $this->assertSame(1.0, $priced['discount_percent']);
    }
}
