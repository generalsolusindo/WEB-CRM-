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
        Schema::create('quotations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->restrictOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->restrictOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['draft', 'sent', 'confirmed', 'revised', 'rejected'])->default('draft');
            $table->integer('revision_number')->default(1);
            $table->foreignId('parent_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
