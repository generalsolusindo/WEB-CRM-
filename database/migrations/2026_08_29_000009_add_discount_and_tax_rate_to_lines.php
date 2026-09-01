<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->decimal('discount_percent', 6, 2)->nullable()->after('selling_price');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
            $table->decimal('tax_rate', 6, 2)->default(0)->after('tax_id');
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('discount_percent', 6, 2)->nullable()->after('selling_price');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
            $table->decimal('tax_rate', 6, 2)->default(0)->after('tax_id');
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('discount_amount', 15, 2)->default(0)->after('unit_price');
            $table->decimal('tax_rate', 6, 2)->default(0)->after('tax_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'tax_rate']);
        });
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'tax_rate']);
        });
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'tax_rate']);
        });
    }
};
