<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->enum('status', ['draft', 'planning', 'waiting_resource', 'ready', 'in_progress', 'verification', 'completed'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
