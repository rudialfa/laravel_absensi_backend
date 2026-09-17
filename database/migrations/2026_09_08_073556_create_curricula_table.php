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
        Schema::create('curricula', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nama kurikulum, contoh: "Kurikulum Merdeka 2026/2027"
            $table->string('name');

            // Tahun ajaran berlaku, contoh: "2026/2027"
            $table->string('academic_year');

            $table->text('description')->nullable();

            // Cuma 1 kurikulum yang aktif per tahun ajaran (dicek di level app,
            // bukan constraint DB, karena butuh logic "nonaktifkan yang lain dulu")
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['company_id', 'academic_year', 'is_active'],
                'idx_curriculums_company_year_active'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('curricula');
    }
};
