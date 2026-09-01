<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoice bisa berdiri tanpa Sales Order (invoice survey).
        DB::statement('ALTER TABLE invoices MODIFY sales_order_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE invoices MODIFY invoice_phase VARCHAR(10) NULL');

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_type', 20)->default('sale')->after('number');
            $table->foreignId('survey_id')->nullable()->after('sales_order_id')
                ->constrained('surveys')->nullOnDelete();
        });

        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('finance_handled_by')->nullable()->after('sourced_at')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('finance_handled_at')->nullable()->after('finance_handled_by');
            $table->string('finance_note', 500)->nullable()->after('finance_handled_at');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finance_handled_by');
            $table->dropColumn(['finance_handled_at', 'finance_note']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('survey_id');
            $table->dropColumn('invoice_type');
        });

        DB::statement('ALTER TABLE invoices MODIFY invoice_phase ENUM(\'dp\',\'full\',\'final\') NOT NULL');
        DB::statement('ALTER TABLE invoices MODIFY sales_order_id BIGINT UNSIGNED NOT NULL');
    }
};
