<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce "one notification per recipient + type + related object" at the DB level
     * so automatic notifications can never be duplicated.
     */
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->unique(['user_id', 'type', 'related_type', 'related_id'], 'notifications_dedupe_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('notifications_dedupe_unique');
        });
    }
};
