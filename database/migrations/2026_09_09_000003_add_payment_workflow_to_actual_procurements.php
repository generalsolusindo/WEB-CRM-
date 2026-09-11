<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->foreignId('procurement_payment_id')->nullable()->after('project_id')
                ->constrained('procurement_payments')->nullOnDelete();
            $table->boolean('from_office_stock')->default(false)->after('cost_price');
            $table->string('office_stock_note', 255)->nullable()->after('from_office_stock');
            $table->boolean('is_paid')->default(false)->after('office_stock_note');
            $table->dateTime('paid_at')->nullable()->after('is_paid');
        });
    }

    public function down(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_payment_id');
            $table->dropColumn(['from_office_stock', 'office_stock_note', 'is_paid', 'paid_at']);
        });
    }
};
