<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Absensi kehadiran (selfie) teknisi di project & surveyor di survey —
     * disimpan sebagai attachment polymorphic seperti kategori lain.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE attachments MODIFY category ENUM(
            'payment_proof','task_before','task_after','bast_document','survey_report',
            'quotation_signed','purchase_order','pph23_slip','checkin_selfie','other'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE attachments SET category = 'other' WHERE category = 'checkin_selfie'");
        DB::statement("ALTER TABLE attachments MODIFY category ENUM(
            'payment_proof','task_before','task_after','bast_document','survey_report',
            'quotation_signed','purchase_order','pph23_slip','other'
        ) NOT NULL");
    }
};
