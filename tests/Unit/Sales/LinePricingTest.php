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
}
