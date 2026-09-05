<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * HR — menjembatani ke teknisi vendor luar dan memverifikasi SOW.
     * Vendor — akun PIC milik vendor eksternal, dibuat & dikelola Procurement,
     * dipakai untuk tanda tangan digital bagian PIC pada dokumen SOW.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'sales','procurement','operational','technician','finance',
            'management','administrator','project_manager','hr','vendor'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM users WHERE role IN ('hr','vendor')");
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'sales','procurement','operational','technician','finance',
            'management','administrator','project_manager'
        ) NOT NULL");
    }
};
