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
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Kelas saat absen ini dicatat — disimpan terpisah dari students.class_id
            // supaya kalau murid pindah kelas, histori absen lama tetap akurat
            $table->foreignId('class_id')
                ->constrained('class_rooms')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->date('date');

            $table->enum('status', ['hadir', 'terlambat', 'izin', 'sakit', 'alpa'])
                ->default('hadir');

            $table->time('check_in_time')->nullable();

            // Foto bukti yang diambil di kiosk saat absen (manual, bukan face-match otomatis)
            $table->string('photo_evidence')->nullable();

            // Guru yang input absen ini lewat kiosk
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Device kiosk asal — nullable karena bisa juga diinput manual dari dashboard admin
            $table->foreignId('device_id')
                ->nullable()
                ->constrained('attendance_devices')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // 1 murid maksimal 1 record absen per hari
            $table->unique(
                ['student_id', 'date'],
                'uniq_student_attendance_date'
            );

            // Query utama: rekap absen per kelas per tanggal (dashboard guru/admin)
            $table->index(
                ['company_id', 'class_id', 'date'],
                'idx_student_attendance_class_date'
            );

            // Filter/rekap berdasarkan status (misal hitung alpa per bulan)
            $table->index('status', 'idx_student_attendance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
