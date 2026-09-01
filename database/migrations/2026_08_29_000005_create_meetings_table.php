<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('title', 255);
            $table->date('meeting_date');
            $table->string('location', 255)->nullable();
            $table->text('attendees')->nullable();
            $table->text('notes');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
