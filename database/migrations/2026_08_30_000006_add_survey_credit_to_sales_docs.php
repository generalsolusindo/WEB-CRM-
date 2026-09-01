<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('survey_credit', 15, 2)->default(0)->after('notes');
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('survey_credit', 15, 2)->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('survey_credit'));
        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn('survey_credit'));
    }
};
