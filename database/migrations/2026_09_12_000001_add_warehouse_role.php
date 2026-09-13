<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Warehouse — mengelola stok barang gudang (CCTV, kabel LAN, dll) supaya
     * Procurement bisa cek ketersediaan langsung dari CRM saat sourcing.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'sales','procurement','operational','technician','finance',
            'management','administrator','project_manager','hr','vendor','warehouse'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM users WHERE role = 'warehouse'");
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'sales','procurement','operational','technician','finance',
            'management','administrator','project_manager','hr','vendor'
        ) NOT NULL");
    }
};
