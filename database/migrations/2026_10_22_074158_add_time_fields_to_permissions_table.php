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
        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'type')) {
                $table->enum('type', [
                    'tidak_masuk',
                    'datang_terlambat',
                    'pulang_cepat',
                    'keluar_kantor',
                    'dinas_luar',
                ])->default('tidak_masuk')->after('user_id');
            }

            // date_permission tetap dipakai sebagai tanggal mulai
            if (!Schema::hasColumn('permissions', 'end_date')) {
                $table->date('end_date')->nullable()->after('date_permission');
            }

            if (!Schema::hasColumn('permissions', 'start_time')) {
                $table->time('start_time')->nullable()->after('end_date');
            }

            if (!Schema::hasColumn('permissions', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }

            // Data approval (dipakai juga untuk detail permission di HR)
            if (!Schema::hasColumn('permissions', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('is_approved')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('permissions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (!Schema::hasColumn('permissions', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }

            $table->index(
                ['user_id', 'date_permission', 'is_approved'],
                'idx_perm_user_date_approved'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropIndex('idx_perm_user_date_approved');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['type', 'end_date', 'start_time', 'end_time', 'approved_at', 'approval_note']);
        });
    }
};
