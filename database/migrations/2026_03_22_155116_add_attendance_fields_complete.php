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

            // company_id — wajib ada, tidak boleh null
            if (!Schema::hasColumn('attendances', 'company_id')) {
                $table->foreignId('company_id')
                    ->after('id')
                    ->constrained('companies')
                    ->cascadeOnDelete();
            }

            // shift_id — nullable, resolve dari shift system
            if (!Schema::hasColumn('attendances', 'shift_id')) {
                $table->foreignId('shift_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('shifts')
                    ->nullOnDelete();
            }

            // Jadwal masuk & keluar dari shift (disimpan saat absen)
            if (!Schema::hasColumn('attendances', 'scheduled_in')) {
                $table->time('scheduled_in')->nullable()->after('shift_id');
            }
            if (!Schema::hasColumn('attendances', 'scheduled_out')) {
                $table->time('scheduled_out')->nullable()->after('scheduled_in');
            }

            // Keterlambatan dalam menit (0 = on time)
            if (!Schema::hasColumn('attendances', 'late_minutes')) {
                $table->unsignedInteger('late_minutes')->default(0)->after('scheduled_out');
            }

            // Pulang lebih awal dalam menit (0 = tidak early leave)
            if (!Schema::hasColumn('attendances', 'early_leave_minutes')) {
                $table->unsignedInteger('early_leave_minutes')->default(0)->after('late_minutes');
            }

            // Apakah absen dilakukan via face scan (true) atau manual HR (false)
            if (!Schema::hasColumn('attendances', 'face_verified')) {
                $table->boolean('face_verified')->default(false)->after('early_leave_minutes');
            }
        });

        // Unique: 1 user hanya boleh 1 attendance per hari per company
        // (pakai try-catch agar tidak error jika sudah ada)
        try {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unique(['company_id', 'user_id', 'date'], 'uniq_att_company_user_date');
            });
        } catch (\Exception $e) {
            // unique sudah ada, skip
        }

        // Index untuk query HR
        try {
            Schema::table('attendances', function (Blueprint $table) {
                $table->index(['company_id', 'date'], 'idx_att_company_date');
                $table->index(['company_id', 'user_id'], 'idx_att_company_user');
            });
        } catch (\Exception $e) {
            // index sudah ada, skip
        }
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Drop indexes dulu
            try {
                $table->dropUnique('uniq_att_company_user_date');
            } catch (\Exception $e) {
            }
            try {
                $table->dropIndex('idx_att_company_date');
            } catch (\Exception $e) {
            }
            try {
                $table->dropIndex('idx_att_company_user');
            } catch (\Exception $e) {
            }

            // Drop kolom
            $cols = ['early_leave_minutes', 'late_minutes', 'scheduled_out', 'scheduled_in', 'face_verified'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('attendances', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('attendances', 'shift_id')) {
                $table->dropConstrainedForeignId('shift_id');
            }
            if (Schema::hasColumn('attendances', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
