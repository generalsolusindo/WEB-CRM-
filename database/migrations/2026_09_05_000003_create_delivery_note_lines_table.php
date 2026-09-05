<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot per baris material yang dikirim di satu delivery note — Ordered
     * & Previous Balance dibekukan saat dibuat, supaya dokumen yang sudah
     * dicetak/ditandatangani tidak berubah walau ada delivery note susulan.
     */
    public function up(): void
    {
        Schema::create('delivery_note_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained('sales_order_lines')->restrictOnDelete();
            $table->string('item_name', 255);
            $table->string('unit', 50);
            $table->decimal('qty_ordered', 15, 2);
            $table->decimal('qty_previous_balance', 15, 2);
            $table->decimal('qty_delivered', 15, 2);
            $table->decimal('qty_balance', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
    }
};
