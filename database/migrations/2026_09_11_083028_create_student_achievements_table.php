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
        Schema::create('student_achievements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('extracurricular_id')->nullable()->constrained('extracurriculars')->nullOnDelete();

            $table->string('title');
            $table->enum('level', ['sekolah', 'kecamatan', 'kabupaten', 'provinsi', 'nasional', 'internasional']);
            $table->string('rank')->nullable(); // Juara 1, Juara 2, dst

            $table->date('achieved_at');
            $table->string('certificate_path')->nullable();

            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'student_id'], 'idx_achievements_company_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_achievements');
    }
};
