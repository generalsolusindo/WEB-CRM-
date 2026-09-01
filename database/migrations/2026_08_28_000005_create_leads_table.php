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
        Schema::create('leads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['lead', 'opportunity'])->default('lead');
            $table->string('stage', 50);
            $table->string('source', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
