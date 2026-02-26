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
        Schema::table('attendances', function (Blueprint $table) {
            // 1) tambah company_id untuk multi-company consistency
            if (!Schema::hasColumn('attendances', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->nullOnDelete();

                $table->index(['company_id', 'date'], 'idx_att_company_date');
            }
        });

        // 2) unique 1 attendance per user per hari
        Schema::table('attendances', function (Blueprint $table) {
            // Pastikan nama index tidak bentrok
            $table->unique(['user_id', 'date'], 'uniq_att_user_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // drop unique
            $table->dropUnique('uniq_att_user_date');

            // drop fk & column (kalau ada)
            if (Schema::hasColumn('attendances', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
                $table->dropIndex('idx_att_company_date');
            }
        });
    }
};
