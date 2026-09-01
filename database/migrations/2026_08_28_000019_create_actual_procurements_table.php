<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actual_procurements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('vendor_product_id')->nullable()->constrained('vendor_products')->nullOnDelete();
            $table->string('item_name', 255);
            $table->decimal('qty', 15, 2);
            $table->string('unit', 50);
            $table->decimal('cost_price', 15, 2);
            $table->enum('status', ['pending', 'purchased', 'received'])->default('pending');
            $table->dateTime('purchased_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actual_procurements');
    }
};
