<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_service_payment_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('vendor_service_payment_id')->constrained('vendor_service_payments')->cascadeOnDelete();
            $table->string('kind', 10); // dp | final
            $table->decimal('amount', 15, 2);
            $table->dateTime('paid_at');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            // Dibatalkan = soft delete: nominal & bukti tetap tersimpan untuk audit, tidak dihitung.
            $table->softDeletes();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_service_payment_entries');
    }
};
