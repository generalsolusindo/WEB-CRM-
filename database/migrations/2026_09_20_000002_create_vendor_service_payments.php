<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Petunjuk dari Sales sejak lead: lokasi di luar jangkauan / kemungkinan butuh vendor luar.
        // Hanya penanda — keputusan & data deal tetap di Procurement (vendor_service_payments).
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('needs_outside_vendor')->default(false)->after('temperature');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('needs_outside_vendor')->default(false)->after('vendor_id');
        });

        Schema::create('vendor_service_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->string('number')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->decimal('total_fee', 15, 2);
            $table->string('terms', 20); // dp_final | pay_at_end
            $table->decimal('dp_amount', 15, 2)->nullable();
            $table->string('bank_name', 100);
            $table->string('account_number', 50);
            $table->string('account_holder', 150);
            $table->text('notes')->nullable();
            $table->string('status', 30);
            $table->dateTime('released_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_service_payments');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('needs_outside_vendor');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('needs_outside_vendor');
        });
    }
};
