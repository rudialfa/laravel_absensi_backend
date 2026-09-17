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
        Schema::create('academic_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // libur = tidak ada KBM, ujian = jadwal ujian, kegiatan = acara sekolah,
            // penting = pengingat umum (rapat, dll)
            $table->enum('jenis', ['libur', 'ujian', 'kegiatan', 'penting']);

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            // Nullable = berlaku untuk semua kelas (mis. libur nasional).
            // Diisi kalau event ini spesifik untuk 1 kelas (mis. ujian kelas 6).
            $table->foreignId('class_id')
                ->nullable()
                ->constrained('class_rooms')
                ->nullOnDelete();

            $table->string('academic_year');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index(
                ['company_id', 'academic_year', 'tanggal_mulai'],
                'idx_events_company_year_tanggal'
            );

            $table->index(
                ['class_id', 'tanggal_mulai'],
                'idx_events_class_tanggal'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_events');
    }
};
