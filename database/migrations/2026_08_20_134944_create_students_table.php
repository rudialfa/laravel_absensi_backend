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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            // Sekolah tempat murid terdaftar
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Kelas saat ini — nullable untuk murid baru yang belum ditempatkan
            $table->foreignId('class_id')
                ->nullable()
                ->constrained('class_rooms')
                ->nullOnDelete();

            // Nomor Induk Siswa (lokal sekolah)
            $table->string('nis');

            // Nomor Induk Siswa Nasional — opsional, tidak semua sekolah input
            $table->string('nisn')->nullable();

            $table->string('name');
            $table->enum('gender', ['L', 'P']);
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();

            // Foto profil murid, ditampilkan di layar kiosk saat absen
            $table->string('photo_url')->nullable();

            $table->text('address')->nullable();

            // Murid ini tinggal di asrama atau pulang-pergi
            // (relevan khusus untuk SD Pondok yang bisa campuran boarding & non-boarding)
            $table->boolean('is_boarding')->default(false);

            $table->date('enrolled_at')->nullable();

            // Aktif / sudah keluar-lulus (soft delete dipakai untuk histori laporan lama)
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Query utama: daftar murid per kelas
            $table->index(
                ['company_id', 'class_id', 'is_active'],
                'idx_students_company_class_active'
            );

            // NIS unik per sekolah (bukan global, karena tiap sekolah tenant sendiri)
            $table->unique(
                ['company_id', 'nis'],
                'uniq_students_company_nis'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
