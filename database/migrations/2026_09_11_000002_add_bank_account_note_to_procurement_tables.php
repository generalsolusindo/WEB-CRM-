<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->text('bank_account_note')->nullable()->after('vendor_id');
        });

        Schema::table('procurement_payments', function (Blueprint $table) {
            $table->text('bank_account_note')->nullable()->after('lump_sum_amount');
        });
    }

    public function down(): void
    {
        Schema::table('actual_procurements', fn (Blueprint $table) => $table->dropColumn('bank_account_note'));
        Schema::table('procurement_payments', fn (Blueprint $table) => $table->dropColumn('bank_account_note'));
    }
};
