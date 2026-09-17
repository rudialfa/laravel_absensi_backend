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
        Schema::create('curriculum_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curricula_id')
                ->constrained('curricula')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            // Berlaku untuk tingkat kelas berapa (1-6) — jadi 1 mapel bisa
            // punya baris berbeda per kelas dengan jam berbeda
            $table->unsignedTinyInteger('grade_level');

            // Jam pelajaran per minggu untuk kombinasi mapel+kelas ini
            $table->unsignedTinyInteger('jam_per_minggu')->default(1);

            $table->timestamps();

            // Kombinasi kurikulum+mapel+kelas harus unik — tidak boleh dobel
            $table->unique(
                ['curricula_id', 'subject_id', 'grade_level'],
                'uniq_curriculum_subject_grade'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
    }
};
