<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['quotation_lines', 'sales_order_lines', 'invoice_lines'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->enum('category', ['material', 'service'])->default('material')->after('item_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['quotation_lines', 'sales_order_lines', 'invoice_lines'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('category'));
        }
    }
};
