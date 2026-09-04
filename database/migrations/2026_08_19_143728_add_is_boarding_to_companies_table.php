<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Pembeda SD Umum vs SD Pondok.
            // false = sekolah umum (murid pulang-pergi)
            // true  = ada layanan asrama/boarding (bisa reuse modul pesantren:
            //         kesantrian, mutabaah_yaumiyah, dsb — cukup digeneralisasi)
            if (!Schema::hasColumn('companies', 'is_boarding')) {
                $table->boolean('is_boarding')->default(false)->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_boarding');
        });
    }
};
