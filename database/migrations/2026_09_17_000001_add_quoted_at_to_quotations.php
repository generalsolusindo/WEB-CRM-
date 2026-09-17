<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Tanggal" yang tercetak di dokumen quotation — beda dari created_at (yang harus tetap
 * jadi tanggal baris ini pertama kali dibuat) dan berbeda dari updated_at (yang ikut
 * berubah tiap kali ada aksi lain seperti review PM/Manager, bukan cuma saat isinya
 * benar-benar diedit). Diisi ulang setiap quotation dibuat atau isinya diedit — sama
 * seperti valid_until, supaya keduanya konsisten sebagai "quotation ini dianggap
 * ditawarkan sejak tanggal ini".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->date('quoted_at')->nullable()->after('valid_until');
        });

        DB::table('quotations')->whereNull('quoted_at')->update(['quoted_at' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('quoted_at');
        });
    }
};
