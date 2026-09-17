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
        Schema::create('uks_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            $table->text('complaint'); // keluhan
            $table->text('treatment')->nullable(); // penanganan
            $table->dateTime('visited_at');

            // pulang = dijemput/pulang setelah ditangani, kembali_kelas = balik belajar
            $table->enum('outcome', ['kembali_kelas', 'pulang', 'dirujuk'])->default('kembali_kelas');

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'student_id', 'visited_at'], 'idx_uks_visits_company_student_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uks_visits');
    }
};
