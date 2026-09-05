<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bast_drafts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->date('event_date')->nullable();
            $table->string('job_title')->nullable();
            $table->text('work_description')->nullable();
            $table->string('pic_name')->nullable();
            $table->string('pic_position')->nullable();
            $table->string('pic_address')->nullable();
            $table->string('leader_name')->nullable();
            $table->string('leader_position')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bast_drafts');
    }
};
