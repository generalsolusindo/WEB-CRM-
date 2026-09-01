<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a human-readable document number to quotations and sales orders.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->unique()->after('id');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('number', 30)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }
};
