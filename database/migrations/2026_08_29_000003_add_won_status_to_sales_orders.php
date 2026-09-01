<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the terminal "won" state used when Sales closes a deal.
     */
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('status', ['confirmed', 'in_progress', 'completed', 'cancelled', 'won'])
                ->default('confirmed')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('status', ['confirmed', 'in_progress', 'completed', 'cancelled'])
                ->default('confirmed')
                ->change();
        });
    }
};
