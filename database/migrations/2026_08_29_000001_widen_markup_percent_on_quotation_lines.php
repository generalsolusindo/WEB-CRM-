<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen markup_percent so large markups (> 999.99%) no longer overflow.
     */
    public function up(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->decimal('markup_percent', 6, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->decimal('markup_percent', 5, 2)->nullable()->change();
        });
    }
};
