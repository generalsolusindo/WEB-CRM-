<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable: "belum ditentukan" ≠ "material", supaya fallback ke kategori produk vendor tetap bekerja.
        Schema::table('requirements', function (Blueprint $table) {
            $table->enum('category', ['material', 'service'])->nullable()->after('item_name');
        });
        Schema::table('procurement_request_lines', function (Blueprint $table) {
            $table->enum('category', ['material', 'service'])->nullable()->after('item_name');
        });
    }

    public function down(): void
    {
        Schema::table('requirements', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('procurement_request_lines', fn (Blueprint $table) => $table->dropColumn('category'));
    }
};
