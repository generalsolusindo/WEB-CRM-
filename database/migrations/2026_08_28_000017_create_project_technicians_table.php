<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_technicians', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_technicians');
    }
};
