<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Login sudah sepenuhnya pindah ke username (lihat migration username sebelumnya) —
     * email cuma jadi data kontak opsional sekarang, bukan lagi identitas wajib. Akun yang
     * dibuat lewat "Manajemen User" Administrator (untuk role-role HQ tanpa email pribadi)
     * tidak boleh dipaksa mengarang email palsu cuma supaya lolos constraint NOT NULL lama.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
