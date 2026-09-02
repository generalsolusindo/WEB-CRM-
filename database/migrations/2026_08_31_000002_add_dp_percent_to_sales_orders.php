<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            // Persentase DP yang dipakai Finance saat menerbitkan invoice muka (null sampai invoice DP dibuat).
            $table->decimal('dp_percent', 5, 2)->nullable()->after('payment_rule');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn('dp_percent'));
    }
};
