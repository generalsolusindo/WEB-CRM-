<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->foreignId('hr_content_reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('hr_content_reviewed_at')->nullable()->after('hr_content_reviewed_by');
            $table->text('hr_content_review_notes')->nullable()->after('hr_content_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hr_content_reviewed_by');
            $table->dropColumn(['hr_content_reviewed_at', 'hr_content_review_notes']);
        });
    }
};
