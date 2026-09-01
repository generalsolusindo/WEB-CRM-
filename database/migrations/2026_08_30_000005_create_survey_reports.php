<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->text('briefing')->nullable()->after('finance_note');
            $table->foreignId('briefed_by')->nullable()->after('briefing')->constrained('users')->nullOnDelete();
            $table->dateTime('briefed_at')->nullable()->after('briefed_by');
        });

        Schema::create('survey_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('survey_id')->unique()->constrained('surveys')->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 20)->default('draft');
            $table->text('summary')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_report_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('survey_report_id')->constrained('survey_reports')->cascadeOnDelete();
            $table->string('item_name', 255);
            $table->decimal('qty', 15, 2)->default(1);
            $table->string('unit', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE attachments MODIFY category ENUM('payment_proof','task_before','task_after','bast_document','survey_report','other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attachments MODIFY category ENUM('payment_proof','task_before','task_after','bast_document','other') NOT NULL");

        Schema::dropIfExists('survey_report_items');
        Schema::dropIfExists('survey_reports');

        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('briefed_by');
            $table->dropColumn(['briefing', 'briefed_at']);
        });
    }
};
