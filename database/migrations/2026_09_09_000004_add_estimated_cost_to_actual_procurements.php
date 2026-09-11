<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->decimal('estimated_cost', 15, 2)->nullable()->after('cost_price');
        });

        // Backfill: pakai cost_price saat ini sebagai estimasi awal untuk baris lama.
        DB::table('actual_procurements')->whereNull('estimated_cost')->update([
            'estimated_cost' => DB::raw('cost_price'),
        ]);
    }

    public function down(): void
    {
        Schema::table('actual_procurements', fn (Blueprint $table) => $table->dropColumn('estimated_cost'));
    }
};
