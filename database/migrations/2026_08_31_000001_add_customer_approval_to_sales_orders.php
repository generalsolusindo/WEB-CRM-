<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('po_number', 100)->nullable()->after('survey_credit');
        });

        DB::statement("ALTER TABLE attachments MODIFY category ENUM('payment_proof','task_before','task_after','bast_document','survey_report','quotation_signed','purchase_order','other') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attachments MODIFY category ENUM('payment_proof','task_before','task_after','bast_document','survey_report','other') NOT NULL");

        Schema::table('sales_orders', fn (Blueprint $table) => $table->dropColumn('po_number'));
    }
};
