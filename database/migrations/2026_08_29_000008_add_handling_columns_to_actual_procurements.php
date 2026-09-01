<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('project_id')->constrained('users');
            $table->foreignId('handled_by')->nullable()->after('status')->constrained('users');
            $table->dateTime('received_at')->nullable()->after('purchased_at');
            $table->text('notes')->nullable()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('actual_procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn(['received_at', 'notes']);
        });
    }
};
