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
        Schema::create('class_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            $table->foreignId('from_class_id')
                ->constrained('class_rooms')
                ->cascadeOnDelete();

            // Nullable karena murid kelas 6 yang lulus tidak punya kelas tujuan
            $table->foreignId('to_class_id')
                ->nullable()
                ->constrained('class_rooms')
                ->nullOnDelete();

            // naik = pindah ke kelas tujuan, tinggal = ulang di grade yang sama,
            // lulus = kelas 6 selesai (tidak ada kelas tujuan)
            $table->enum('status', ['naik', 'tinggal', 'lulus']);

            $table->string('from_academic_year');
            $table->string('to_academic_year');

            $table->foreignId('processed_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index(
                ['company_id', 'from_class_id', 'to_academic_year'],
                'idx_promotions_company_class_year'
            );

            $table->index(
                ['student_id', 'to_academic_year'],
                'idx_promotions_student_year'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_promotions');
    }
};
