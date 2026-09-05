<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('pm_review_status')->nullable()->after('agreed_dpp');
            $table->foreignId('pm_reviewed_by')->nullable()->after('pm_review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('pm_reviewed_at')->nullable()->after('pm_reviewed_by');
            $table->text('pm_review_notes')->nullable()->after('pm_reviewed_at');

            $table->string('manager_review_status')->nullable()->after('pm_review_notes');
            $table->foreignId('manager_reviewed_by')->nullable()->after('manager_review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('manager_reviewed_at')->nullable()->after('manager_reviewed_by');
            $table->text('manager_review_notes')->nullable()->after('manager_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pm_reviewed_by');
            $table->dropConstrainedForeignId('manager_reviewed_by');
            $table->dropColumn([
                'pm_review_status', 'pm_reviewed_at', 'pm_review_notes',
                'manager_review_status', 'manager_reviewed_at', 'manager_review_notes',
            ]);
        });
    }
};
