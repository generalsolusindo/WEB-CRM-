<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Delegasi ke Project Manager dimulai sejak Opportunity — satu delegasi
     * yang sama, bukan terpisah per tahap. Saat Project otomatis terbentuk
     * nanti, nilai ini disalin ke projects.delegated_to (lihat InitializeProject).
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('delegated_to')->nullable()->after('notes')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by')->nullable()->after('delegated_to')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('delegated_at')->nullable()->after('delegated_by');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegated_to');
            $table->dropConstrainedForeignId('delegated_by');
            $table->dropColumn('delegated_at');
        });
    }
};
