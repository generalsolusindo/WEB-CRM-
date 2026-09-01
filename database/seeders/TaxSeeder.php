<?php

namespace Database\Seeders;

use App\Models\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['name' => 'PPN 11%', 'rate' => 11, 'is_active' => true],
            ['name' => 'PPN 12%', 'rate' => 12, 'is_active' => true],
        ];

        foreach ($taxes as $tax) {
            Tax::firstOrCreate(['name' => $tax['name']], $tax);
        }
    }
}
