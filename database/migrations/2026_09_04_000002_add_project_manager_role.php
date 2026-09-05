<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Project Manager — akun bawahan Manager, dipakai untuk delegasi project
     * tertentu saat Manager kewalahan menangani semuanya sendiri.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'sales','procurement','operational','technician','finance',
            'management','administrator','project_manager'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET role = 'management' WHERE role = 'project_manager'");
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'sales','procurement','operational','technician','finance',
            'management','administrator'
        ) NOT NULL");
    }
};
