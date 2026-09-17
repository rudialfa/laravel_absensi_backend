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
        Schema::create('room_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('room_id')
                ->constrained('dormitory_rooms')
                ->cascadeOnDelete();

            // Tanggal mulai menempati kamar ini
            $table->date('moved_in_at');

            // Nullable = masih menempati kamar ini sekarang.
            // Diisi otomatis saat santri dipindah ke kamar lain atau keluar asrama.
            $table->date('moved_out_at')->nullable();

            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Query utama: cek penempatan aktif 1 santri (moved_out_at null)
            $table->index(
                ['student_id', 'moved_out_at'],
                'idx_assignments_student_active'
            );

            // Query utama: daftar penghuni 1 kamar yang masih aktif
            $table->index(
                ['room_id', 'moved_out_at'],
                'idx_assignments_room_active'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_assignments');
    }
};
