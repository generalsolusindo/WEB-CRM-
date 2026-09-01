<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            // Diisi Sales saat minta survey.
            $table->text('site_address');
            $table->string('site_region', 160);
            $table->enum('delivery_mode', ['internal', 'vendor']);
            $table->boolean('billable')->default(false);
            $table->text('notes')->nullable();

            $table->string('status', 30)->default('requested');

            // Diisi Procurement saat sourcing.
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('surveyor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('cost', 15, 2)->default(0);
            $table->foreignId('sourced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('sourced_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
