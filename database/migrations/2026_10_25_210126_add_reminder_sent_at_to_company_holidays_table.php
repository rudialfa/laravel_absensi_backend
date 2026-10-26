<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda pengingat H-1 sudah dikirim, supaya karyawan tidak
     * menerima pengingat dobel kalau scheduler jalan lebih dari sekali.
     */
    public function up(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            if (!Schema::hasColumn('company_holidays', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
