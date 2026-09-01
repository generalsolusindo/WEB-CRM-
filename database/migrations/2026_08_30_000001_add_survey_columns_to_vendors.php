<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('city', 120)->nullable()->after('address');
            $table->text('coverage_area')->nullable()->after('city');
            $table->boolean('provides_survey')->default(false)->after('coverage_area');
            $table->boolean('provides_technical')->default(false)->after('provides_survey');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['city', 'coverage_area', 'provides_survey', 'provides_technical']);
        });
    }
};
