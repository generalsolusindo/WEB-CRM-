<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bast', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->enum('status', ['submitted', 'verified', 'rejected'])->default('submitted');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->dateTime('submitted_at');
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bast');
    }
};
