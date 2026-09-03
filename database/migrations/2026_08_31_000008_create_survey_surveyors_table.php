<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_surveyors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->timestamps();
            $table->unique(['survey_id', 'technician_id']);
        });

        // Surveyor tunggal yang sudah ada -> jadi leader.
        foreach (DB::table('surveys')->whereNotNull('surveyor_id')->get(['id', 'surveyor_id']) as $survey) {
            DB::table('survey_surveyors')->insert([
                'survey_id' => $survey->id,
                'technician_id' => $survey->surveyor_id,
                'is_leader' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surveyor_id');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('surveyor_id')->nullable()->after('vendor_id')
                ->constrained('users')->nullOnDelete();
        });

        foreach (DB::table('survey_surveyors')->where('is_leader', true)->get() as $row) {
            DB::table('surveys')->where('id', $row->survey_id)->update(['surveyor_id' => $row->technician_id]);
        }

        Schema::dropIfExists('survey_surveyors');
    }
};
