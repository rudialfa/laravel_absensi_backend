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
        Schema::create('class_teachers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('class_id')
                ->constrained('class_rooms')
                ->cascadeOnDelete();

            // User dengan role = 'guru'
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // wali_kelas = pemegang absen utama & yang pegang kiosk kelas ini
            // guru_mapel = guru mata pelajaran tertentu di kelas ini
            $table->enum('role_in_class', ['wali_kelas', 'guru_mapel'])
                ->default('guru_mapel');

            // Nama mata pelajaran, hanya relevan kalau role_in_class = guru_mapel
            $table->string('subject')->nullable();

            $table->timestamps();

            // Satu guru tidak boleh dobel untuk mapel yang sama di kelas yang sama
            $table->unique(
                ['class_id', 'user_id', 'subject'],
                'uniq_class_teacher_subject'
            );

            // Query utama: kelas-kelas yang diampu 1 guru
            $table->index('user_id', 'idx_class_teacher_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_teachers');
    }
};
