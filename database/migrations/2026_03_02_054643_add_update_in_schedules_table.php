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
        Schema::table('schedules', function (Blueprint $table) {

            // Tambah company_id (jika belum ada)
            if (!Schema::hasColumn('schedules', 'company_id')) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->nullOnDelete();
            }

            // Siapa yang membuat jadwal (HR atau karyawan sendiri)
            if (!Schema::hasColumn('schedules', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            // Waktu selesai jadwal
            if (!Schema::hasColumn('schedules', 'end_datetime')) {
                $table->dateTime('end_datetime')
                    ->nullable()
                    ->after('start_datetime');
            }

            // Ganti is_task_duty boolean -> enum type yang lebih fleksibel
            if (!Schema::hasColumn('schedules', 'type')) {
                $table->enum('type', ['meeting', 'task_duty', 'visit', 'training', 'other'])
                    ->default('meeting')
                    ->after('description');
            }

            // Recurring / jadwal berulang
            if (!Schema::hasColumn('schedules', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('status');
            }

            if (!Schema::hasColumn('schedules', 'recurrence_type')) {
                $table->enum('recurrence_type', ['daily', 'weekly', 'monthly'])
                    ->nullable()
                    ->after('is_recurring');
            }

            if (!Schema::hasColumn('schedules', 'recurrence_end_date')) {
                $table->date('recurrence_end_date')
                    ->nullable()
                    ->after('recurrence_type');
            }

            // Soft delete untuk audit trail
            $table->softDeletes();

            // Index untuk performa query
            $table->index(['user_id', 'start_datetime'], 'idx_schedule_user_start');
            $table->index(['company_id', 'start_datetime'], 'idx_schedule_company_start');
            $table->index('status', 'idx_schedule_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('idx_schedule_user_start');
            $table->dropIndex('idx_schedule_company_start');
            $table->dropIndex('idx_schedule_status');
            $table->dropSoftDeletes();
            $table->dropColumn([
                'company_id',
                'created_by',
                'end_datetime',
                'type',
                'is_recurring',
                'recurrence_type',
                'recurrence_end_date',
            ]);
        });
    }
};
