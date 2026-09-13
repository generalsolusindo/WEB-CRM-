<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->string('schedule_duration')->nullable()->after('schedule');
            $table->date('schedule_start_date')->nullable()->after('schedule_duration');
            $table->date('schedule_end_date')->nullable()->after('schedule_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->dropColumn(['schedule_duration', 'schedule_start_date', 'schedule_end_date']);
        });
    }
};
