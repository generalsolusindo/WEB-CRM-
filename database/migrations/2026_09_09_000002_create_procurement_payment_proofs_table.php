<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_payment_proofs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('procurement_payment_id')->constrained('procurement_payments')->cascadeOnDelete();
            $table->foreignId('actual_procurement_id')->nullable()->constrained('actual_procurements')->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->dateTime('uploaded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_payment_proofs');
    }
};
