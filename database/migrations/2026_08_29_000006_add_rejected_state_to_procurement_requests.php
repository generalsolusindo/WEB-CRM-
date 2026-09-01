<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'searching', 'ready', 'rejected'])
                ->default('draft')
                ->change();
            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
            $table->enum('status', ['draft', 'submitted', 'searching', 'ready'])
                ->default('draft')
                ->change();
        });
    }
};
