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
        Schema::create('procurement_request_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->foreignId('vendor_product_id')->nullable()->constrained('vendor_products')->nullOnDelete();
            $table->string('item_name', 255);
            $table->text('description')->nullable();
            $table->decimal('qty', 15, 2);
            $table->string('unit', 50);
            $table->decimal('cost_price', 15, 2);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->enum('availability_status', ['available', 'unavailable', 'searching'])->default('searching');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_request_lines');
    }
};
