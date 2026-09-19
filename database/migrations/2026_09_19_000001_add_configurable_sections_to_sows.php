<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->json('section_visibility')->nullable()->after('closing');
            $table->json('custom_sections')->nullable()->after('section_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('sows', function (Blueprint $table) {
            $table->dropColumn(['section_visibility', 'custom_sections']);
        });
    }
};
