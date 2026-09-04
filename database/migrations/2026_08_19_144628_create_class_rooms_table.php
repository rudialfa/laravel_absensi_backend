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
        Schema::create('class_rooms', function (Blueprint $table) {
            $table->id();

            // Sekolah pemilik kelas ini
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nama kelas, contoh: "1A", "6B"
            $table->string('name');

            // Tingkat kelas SD: 1-6
            $table->unsignedTinyInteger('grade_level');

            // Tahun ajaran, contoh: "2026/2027"
            $table->string('academic_year');

            // Wali kelas (guru) — nullable karena bisa belum ditentukan saat kelas dibuat
            $table->foreignId('homeroom_teacher_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Kelas aktif tahun ajaran berjalan atau arsip tahun lalu
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Query utama: daftar kelas aktif per sekolah per tahun ajaran
            $table->index(
                ['company_id', 'academic_year', 'is_active'],
                'idx_classes_company_year_active'
            );

            // Satu nama kelas hanya boleh 1x per sekolah per tahun ajaran
            $table->unique(
                ['company_id', 'name', 'academic_year'],
                'uniq_classes_company_name_year'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_rooms');
    }
};
