<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->longText('technician_signature')->nullable();
            $table->timestamp('technician_signed_at')->nullable();

            $table->longText('vendor_signature')->nullable();
            $table->timestamp('vendor_signed_at')->nullable();
            $table->foreignId('vendor_signed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('hr_signature_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_signature_reviewed_at')->nullable();
            $table->text('hr_signature_review_notes')->nullable();

            $table->longText('admin_signature')->nullable();
            $table->timestamp('admin_signed_at')->nullable();
            $table->foreignId('admin_signed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->longText('director_signature')->nullable();
            $table->timestamp('director_signed_at')->nullable();
            $table->foreignId('director_signed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_signed_by');
            $table->dropConstrainedForeignId('hr_signature_reviewed_by');
            $table->dropConstrainedForeignId('admin_signed_by');
            $table->dropConstrainedForeignId('director_signed_by');
            $table->dropColumn([
                'technician_signature', 'technician_signed_at',
                'vendor_signature', 'vendor_signed_at',
                'hr_signature_reviewed_at', 'hr_signature_review_notes',
                'admin_signature', 'admin_signed_at',
                'director_signature', 'director_signed_at',
            ]);
        });
    }
};
