<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Default true supaya semua invoice yang sudah ada tetap tampil persis seperti
            // sekarang (nama + stempel/ttd digital + jabatan). Finance mematikannya khusus
            // untuk invoice yang klien-nya minta cetak fisik untuk tanda tangan basah + materai.
            $table->boolean('with_signature')->default(true)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('with_signature');
        });
    }
};
