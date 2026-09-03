<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai DPP disepakati (harga nett yang dinegosiasikan). Bila diisi, diskon
     * dokumen dihitung mundur = Σ bruto baris − agreed_dpp lalu dibagi rata
     * proporsional ke tiap baris, menimpa diskon per-baris.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('agreed_dpp', 15, 2)->nullable()->after('survey_credit');
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('agreed_dpp', 15, 2)->nullable()->after('survey_credit');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('agreed_dpp'));
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn('agreed_dpp'));
    }
};
