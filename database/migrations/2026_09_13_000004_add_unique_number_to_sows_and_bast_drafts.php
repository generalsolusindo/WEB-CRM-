<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->unique('number');
        });

        Schema::table('bast_drafts', function (Blueprint $table) {
            $table->unique('number');
        });
    }

    public function down(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->dropUnique(['number']);
        });

        Schema::table('bast_drafts', function (Blueprint $table) {
            $table->dropUnique(['number']);
        });
    }
};
