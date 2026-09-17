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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('class_id')
                ->constrained('class_rooms')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // tugas = nilai harian/tugas, ujian = UTS/UAS/ulangan
            $table->enum('jenis', ['tugas', 'ujian']);

            // Judul spesifik, contoh: "Tugas Bab 3", "UTS Semester Ganjil"
            $table->string('judul');

            $table->decimal('nilai', 5, 2); // 0.00 - 100.00

            $table->date('tanggal');

            // ganjil/genap — dipakai buat kelompokkan rekap per semester
            $table->enum('semester', ['ganjil', 'genap']);
            $table->string('academic_year');

            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'student_id', 'subject_id', 'semester', 'academic_year'],
                'idx_grades_student_subject_semester'
            );

            $table->index(
                ['class_id', 'subject_id', 'semester', 'academic_year'],
                'idx_grades_class_subject_semester'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
