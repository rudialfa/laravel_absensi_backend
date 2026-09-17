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
        Schema::create('lesson_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('class_id')
                ->constrained('class_rooms')
                ->cascadeOnDelete();

            $table->foreignId('subject_id')
                ->constrained('subjects')
                ->cascadeOnDelete();

            // Guru pengampu mapel ini di jam tersebut
            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu']);

            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->string('academic_year');

            $table->timestamps();

            $table->index(
                ['company_id', 'class_id', 'hari', 'academic_year'],
                'idx_schedules_company_class_hari_year'
            );

            $table->index(
                ['teacher_id', 'hari', 'academic_year'],
                'idx_schedules_teacher_hari_year'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_schedules');
    }
};
