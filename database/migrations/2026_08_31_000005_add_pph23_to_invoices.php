<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('pph23_rate', 5, 2)->default(0)->after('tax_amount');
            $table->decimal('pph23_amount', 15, 2)->default(0)->after('pph23_rate');
            $table->string('pph23_bukti_potong_no', 100)->nullable()->after('pph23_amount');
            $table->dateTime('pph23_recorded_at')->nullable()->after('pph23_bukti_potong_no');
            $table->foreignId('pph23_recorded_by')->nullable()->after('pph23_recorded_at')
                ->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE attachments MODIFY category ENUM('payment_proof','task_before','task_after','bast_document','survey_report','quotation_signed','purchase_order','pph23_slip','other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attachments MODIFY category ENUM('payment_proof','task_before','task_after','bast_document','survey_report','quotation_signed','purchase_order','other') NOT NULL");

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pph23_recorded_by');
            $table->dropColumn(['pph23_rate', 'pph23_amount', 'pph23_bukti_potong_no', 'pph23_recorded_at']);
        });
    }
};
