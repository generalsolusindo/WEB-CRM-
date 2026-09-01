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
        Schema::create('vendor_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('item_name', 255);
            $table->enum('category', ['material', 'service']);
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->string('unit', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_products');
    }
};
