<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_request_lines', function (Blueprint $table) {
            $table->text('sourcing_note')->nullable()->after('vendor_product_id');
        });
        Schema::table('quotation_lines', function (Blueprint $table) {
            $table->text('sourcing_note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_request_lines', fn (Blueprint $table) => $table->dropColumn('sourcing_note'));
        Schema::table('quotation_lines', fn (Blueprint $table) => $table->dropColumn('sourcing_note'));
    }
};
