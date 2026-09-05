<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manager bisa mendelegasikan sebuah project ke satu akun Project Manager
     * saat kewalahan menangani semuanya sendiri. Eksekusi harian (planning,
     * task, BAST) tetap di tangan Operational — delegasi ini cuma memindahkan
     * "siapa yang mengawasi/approve" project itu dari Manager ke PM tersebut.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('delegated_to')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by')->nullable()->after('delegated_to')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable()->after('delegated_by');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegated_to');
            $table->dropConstrainedForeignId('delegated_by');
            $table->dropColumn('delegated_at');
        });
    }
};
