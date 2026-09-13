<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->foreignId('warehouse_item_id')->nullable()->after('office_stock_note')
                ->constrained('warehouse_items')->nullOnDelete();
            $table->unsignedInteger('warehouse_qty')->nullable()->after('warehouse_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_item_id');
            $table->dropColumn('warehouse_qty');
        });
    }
};
