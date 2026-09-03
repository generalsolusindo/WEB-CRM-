<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PPh 23 tidak lagi otomatis untuk tiap invoice jasa. Finance menandai
     * eksplisit "customer memotong PPh 23" saat membuat invoice; potongannya
     * lalu dibagi proporsional ke tiap invoice (DP & pelunasan).
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('pph23_enabled')->default(false)->after('status');
        });

        // Invoice lama yang sudah punya potongan PPh 23 dianggap "enabled".
        DB::table('invoices')->where('pph23_amount', '>', 0)->update(['pph23_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('pph23_enabled'));
    }
};
