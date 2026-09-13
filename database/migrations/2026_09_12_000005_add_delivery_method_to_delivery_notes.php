<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->enum('delivery_method', ['ekspedisi', 'sendiri'])->nullable()->after('sales_order_id');
            $table->string('tracking_number', 100)->nullable()->after('shipper_name');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn(['delivery_method', 'tracking_number']);
        });
    }
};
