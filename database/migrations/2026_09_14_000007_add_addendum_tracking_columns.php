<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            // Ditandai begitu requirement ikut disertakan dalam sebuah Procurement Request
            // (submission awal ATAU submission tambahan/addendum) — dipakai untuk membedakan
            // requirement baru yang belum pernah diproses, supaya submission tambahan tidak
            // menyertakan ulang requirement lama yang sudah pernah dikirim.
            $table->timestamp('submitted_at')->nullable()->after('notes');
        });

        // Requirement lama dianggap sudah pernah disubmit HANYA kalau lead-nya memang
        // sudah pernah punya Procurement Request — supaya requirement yang masih
        // draft (belum pernah dikirim sama sekali) tetap bisa disubmit normal,
        // tidak salah ikut terkunci seolah sudah pernah dikirim.
        DB::statement('
            update requirements
            set submitted_at = now()
            where lead_id in (select distinct lead_id from procurement_requests)
        ');

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->boolean('is_addendum')->default(false)->after('notes');
            $table->foreignId('addendum_of_sales_order_id')->nullable()->after('is_addendum')
                ->constrained('sales_orders')->nullOnDelete();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->boolean('is_addendum')->default(false)->after('parent_quotation_id');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->foreignId('addendum_of_sales_order_id')->nullable()->after('quotation_id')
                ->constrained('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('addendum_of_sales_order_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('is_addendum');
        });

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('addendum_of_sales_order_id');
            $table->dropColumn('is_addendum');
        });

        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
