<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('cancel_reason', 500)->nullable()->after('briefed_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')
                ->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancel_reason', 'cancelled_at']);
        });
    }
};
