<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique (company_id, user_id, date) membuat karyawan TIDAK BISA mengajukan
     * ulang lembur di tanggal yang sama setelah pengajuan sebelumnya dibatalkan
     * atau ditolak (muncul error 500 "Duplicate entry").
     *
     * Aturan "1 lembur aktif per hari" tetap dijaga di controller
     * (hanya status pending/approved yang dihitung).
     */
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            // Index biasa dulu supaya FK company_id tetap punya index
            $table->index(['company_id', 'user_id', 'date'], 'idx_ot_company_user_date');
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropUnique('uniq_ot_company_user_date');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->unique(['company_id', 'user_id', 'date'], 'uniq_ot_company_user_date');
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropIndex('idx_ot_company_user_date');
        });
    }
};
