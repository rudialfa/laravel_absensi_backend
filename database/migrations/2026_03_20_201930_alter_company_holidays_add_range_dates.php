<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('company_id');
            $table->date('end_date')->nullable()->after('start_date');
        });

        DB::statement("
            UPDATE company_holidays
            SET start_date = date, end_date = date
            WHERE start_date IS NULL AND end_date IS NULL
        ");

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();

            // buat index pengganti dulu agar FK company_id tetap aman
            $table->index(['company_id', 'start_date'], 'idx_company_holiday_start');
            $table->index(['company_id', 'end_date'], 'idx_company_holiday_end');
            $table->index('company_id', 'idx_company_holiday_company_id');
        });

        Schema::table('company_holidays', function (Blueprint $table) {
            // baru drop index lama
            $table->dropUnique('uniq_company_holiday_date');
            $table->dropIndex('idx_company_holiday_date');

            // lalu drop kolom lama
            $table->dropColumn('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_holidays', function (Blueprint $table) {
            $table->date('date')->nullable()->after('company_id');
        });

        DB::statement("
            UPDATE company_holidays
            SET date = start_date
            WHERE date IS NULL
        ");

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->date('date')->nullable(false)->change();

            $table->unique(['company_id', 'date'], 'uniq_company_holiday_date');
            $table->index(['company_id', 'date'], 'idx_company_holiday_date');
        });

        Schema::table('company_holidays', function (Blueprint $table) {
            $table->dropIndex('idx_company_holiday_start');
            $table->dropIndex('idx_company_holiday_end');
            $table->dropIndex('idx_company_holiday_company_id');

            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
