<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biaya survey dan tagihan order adalah dua hal yang benar-benar terpisah —
     * tidak saling potong. Fitur "Kredit Biaya Survey" dihapus.
     */
    public function up(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('survey_credit'));
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn('survey_credit'));
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->decimal('survey_credit', 15, 2)->default(0)->after('notes'));
        Schema::table('sales_orders', fn (Blueprint $table) => $table->decimal('survey_credit', 15, 2)->default(0)->after('status'));
    }
};
