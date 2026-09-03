<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kategori baris menjadi tiga: material, service (jasa — kena PPh 23),
     * dan reimburse (penggantian biaya seperti transport & akomodasi — tampil
     * di grup jasa tapi tidak kena PPh 23, umumnya tanpa markup).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE requirements MODIFY category ENUM('material','service','reimburse') NULL");
        DB::statement("ALTER TABLE procurement_request_lines MODIFY category ENUM('material','service','reimburse') NULL");
        DB::statement("ALTER TABLE quotation_lines MODIFY category ENUM('material','service','reimburse') NOT NULL DEFAULT 'material'");
        DB::statement("ALTER TABLE sales_order_lines MODIFY category ENUM('material','service','reimburse') NOT NULL DEFAULT 'material'");
        DB::statement("ALTER TABLE invoice_lines MODIFY category ENUM('material','service','reimburse') NOT NULL DEFAULT 'material'");
        DB::statement("ALTER TABLE vendor_products MODIFY category ENUM('material','service','reimburse') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE requirements SET category = NULL WHERE category = 'reimburse'");
        DB::statement("UPDATE procurement_request_lines SET category = NULL WHERE category = 'reimburse'");
        DB::statement("UPDATE quotation_lines SET category = 'material' WHERE category = 'reimburse'");
        DB::statement("UPDATE sales_order_lines SET category = 'material' WHERE category = 'reimburse'");
        DB::statement("UPDATE invoice_lines SET category = 'material' WHERE category = 'reimburse'");
        DB::statement("UPDATE vendor_products SET category = 'material' WHERE category = 'reimburse'");

        DB::statement("ALTER TABLE requirements MODIFY category ENUM('material','service') NULL");
        DB::statement("ALTER TABLE procurement_request_lines MODIFY category ENUM('material','service') NULL");
        DB::statement("ALTER TABLE quotation_lines MODIFY category ENUM('material','service') NOT NULL DEFAULT 'material'");
        DB::statement("ALTER TABLE sales_order_lines MODIFY category ENUM('material','service') NOT NULL DEFAULT 'material'");
        DB::statement("ALTER TABLE invoice_lines MODIFY category ENUM('material','service') NOT NULL DEFAULT 'material'");
        DB::statement("ALTER TABLE vendor_products MODIFY category ENUM('material','service') NOT NULL");
    }
};
