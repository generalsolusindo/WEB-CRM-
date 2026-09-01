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
        Schema::create('invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->enum('invoice_phase', ['dp', 'full', 'final']);
            $table->enum('status', ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled'])->default('draft');
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2);
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
