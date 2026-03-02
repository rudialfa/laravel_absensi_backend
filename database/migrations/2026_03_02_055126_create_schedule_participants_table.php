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
        Schema::create('schedule_participants', function (Blueprint $table) {
            $table->id();
            // Relasi ke jadwal
            $table->foreignId('schedule_id')
                ->constrained('schedules')
                ->cascadeOnDelete();

            // Peserta (karyawan)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Status undangan peserta
            $table->enum('status', ['invited', 'accepted', 'declined'])
                ->default('invited');

            // Catatan dari peserta (opsional, misal: "tidak bisa hadir karena...")
            $table->text('note')->nullable();

            // Waktu peserta merespon undangan
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            // Satu user hanya bisa sekali di satu jadwal
            $table->unique(['schedule_id', 'user_id'], 'uniq_schedule_participant');

            // Index untuk query cepat
            $table->index(['schedule_id', 'status'], 'idx_sp_schedule_status');
            $table->index(['user_id', 'status'], 'idx_sp_user_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_participants');
    }
};
