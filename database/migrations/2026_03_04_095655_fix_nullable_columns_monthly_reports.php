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
        Schema::table('monthly_reports', function (Blueprint $table) {
            // Jadikan nullable semua kolom yang mungkin tidak diisi saat create
            if (Schema::hasColumn('monthly_reports', 'target')) {
                $table->text('target')->nullable()->change();
            }
            if (Schema::hasColumn('monthly_reports', 'achievement')) {
                $table->text('achievement')->nullable()->change();
            }
            if (Schema::hasColumn('monthly_reports', 'problem')) {
                $table->text('problem')->nullable()->change();
            }
            if (Schema::hasColumn('monthly_reports', 'solution')) {
                $table->text('solution')->nullable()->change();
            }
            if (Schema::hasColumn('monthly_reports', 'notes')) {
                $table->text('notes')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            //
        });
    }
};
