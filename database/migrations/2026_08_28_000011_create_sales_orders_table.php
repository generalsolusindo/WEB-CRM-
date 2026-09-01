<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->enum('order_type', ['material_only', 'service_only', 'mixed']);
            $table->enum('payment_rule', ['full_100', 'dp_50']);
            $table->enum('status', ['confirmed', 'in_progress', 'completed', 'cancelled'])->default('confirmed');
            $table->dateTime('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
