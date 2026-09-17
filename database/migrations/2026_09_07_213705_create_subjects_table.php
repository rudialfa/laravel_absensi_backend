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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nama mapel, contoh: "Matematika", "Bahasa Indonesia"
            $table->string('name');

            // Kode singkat, contoh: "MTK", "BINDO" — dipakai di rapor/laporan
            $table->string('code', 20)->nullable();

            // Kelompok mapel: wajib (kurikulum nasional) atau muatan_lokal
            $table->enum('kelompok', ['wajib', 'muatan_lokal'])->default('wajib');

            // Berlaku untuk kelas berapa saja — nullable = semua kelas 1-6
            $table->unsignedTinyInteger('grade_level_min')->nullable();
            $table->unsignedTinyInteger('grade_level_max')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(
                ['company_id', 'is_active'],
                'idx_subjects_company_active'
            );

            // Nama mapel unik per sekolah
            $table->unique(
                ['company_id', 'name'],
                'uniq_subjects_company_name'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
