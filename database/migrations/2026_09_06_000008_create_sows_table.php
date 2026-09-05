<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sows', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('status')->default('draft');

            $table->string('number')->nullable();
            $table->string('project_name')->nullable();
            $table->string('site_location')->nullable();
            $table->string('client_name')->nullable();
            $table->string('execution_date')->nullable();

            $table->text('background')->nullable();
            $table->text('scope_pre_work')->nullable();
            $table->text('scope_other')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('schedule')->nullable();
            $table->text('safety')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('output')->nullable();
            $table->text('warranty')->nullable();
            $table->text('notes')->nullable();
            $table->text('closing')->nullable();

            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('technician_team_note')->nullable();
            $table->string('client_pic_name')->nullable();
            $table->string('client_pic_phone')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sows');
    }
};
