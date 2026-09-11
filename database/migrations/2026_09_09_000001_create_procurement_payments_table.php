<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('number', 50)->unique();
            $table->enum('status', [
                'draft', 'pending_pm', 'rejected_pm', 'approved_pm', 'paid', 'confirmed',
            ])->default('draft');
            $table->enum('pricing_mode', ['itemized', 'lump_sum'])->default('itemized');
            $table->foreignId('lump_sum_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->decimal('lump_sum_amount', 15, 2)->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('pm_reviewed_by')->nullable()->constrained('users');
            $table->dateTime('pm_reviewed_at')->nullable();
            $table->text('pm_notes')->nullable();
            $table->foreignId('finance_paid_by')->nullable()->constrained('users');
            $table->dateTime('finance_paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_payments');
    }
};
