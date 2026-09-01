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
        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('procurement_request_line_id')->nullable()->constrained('procurement_request_lines')->nullOnDelete();
            $table->string('item_name', 255);
            $table->text('description')->nullable();
            $table->decimal('qty', 15, 2);
            $table->string('unit', 50);
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('markup_percent', 5, 2)->nullable();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_lines');
    }
};
