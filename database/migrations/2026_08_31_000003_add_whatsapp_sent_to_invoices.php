<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dateTime('whatsapp_sent_at')->nullable()->after('due_date');
            $table->foreignId('whatsapp_sent_by')->nullable()->after('whatsapp_sent_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_sent_by');
            $table->dropColumn('whatsapp_sent_at');
        });
    }
};
