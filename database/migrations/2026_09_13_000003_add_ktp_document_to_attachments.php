<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attachments MODIFY category ENUM(
            'payment_proof','task_before','task_after','bast_document','survey_report',
            'quotation_signed','purchase_order','pph23_slip','checkin_selfie','sow_background',
            'checkout_selfie','delivery_dispatch_proof','delivery_received_proof','npwp_document',
            'ktp_document','other'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE attachments SET category = 'other' WHERE category = 'ktp_document'");
        DB::statement("ALTER TABLE attachments MODIFY category ENUM(
            'payment_proof','task_before','task_after','bast_document','survey_report',
            'quotation_signed','purchase_order','pph23_slip','checkin_selfie','sow_background',
            'checkout_selfie','delivery_dispatch_proof','delivery_received_proof','npwp_document','other'
        ) NOT NULL");
    }
};
