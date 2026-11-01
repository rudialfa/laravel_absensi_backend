<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - work_days             : hari kerja perusahaan, diatur HR.
     *                           Format angka ISO: 1=Senin ... 7=Minggu
     *                           Contoh Senin–Jumat: [1,2,3,4,5]
     * - notification_settings : pengaturan pengingat & notifikasi (JSON)
     *
     * Kosong (null) = pakai nilai default (lihat App\Support\CompanySettings).
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'work_days')) {
                $table->json('work_days')->nullable()->after('time_out');
            }
            if (!Schema::hasColumn('companies', 'notification_settings')) {
                $table->json('notification_settings')->nullable()->after('work_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['work_days', 'notification_settings']);
        });
    }
};
