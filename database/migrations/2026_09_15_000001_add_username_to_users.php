<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Login berpindah dari email ke username (huruf kecil). Kolom email tetap
     * disimpan untuk data kontak, tapi tidak lagi dipakai untuk autentikasi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        // Backfill baris yang sudah ada supaya tidak ada username kosong,
        // dari local-part email (atau slug nama kalau email kosong/bentrok).
        $used = [];
        foreach (DB::table('users')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            $base = Str::of($user->email ?? '')->before('@')->lower()->slug('');
            if ($base === '' || $base === null) {
                $base = Str::of($user->name)->lower()->slug('');
            }
            $base = (string) $base ?: 'user'.$user->id;

            $candidate = $base;
            $suffix = 1;
            while (in_array($candidate, $used, true)) {
                $suffix++;
                $candidate = $base.$suffix;
            }
            $used[] = $candidate;

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
