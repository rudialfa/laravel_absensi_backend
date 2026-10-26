<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - approval_note : alasan ditolak / catatan HR (sebelumnya tidak ada,
     *                   karyawan tidak tahu kenapa laporannya ditolak)
     * - submitted_at  : kapan laporan dikirim ke HR
     *
     * Tabel ini juga dipakai modul pesantren, jadi semua kolom nullable (aman).
     */
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('monthly_reports', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('monthly_reports', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'approval_note']);
        });
    }
};
