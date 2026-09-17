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
        Schema::create('tahfidz_setorans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // Guru/pembimbing yang menerima setoran hari itu
            $table->foreignId('guru_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('juz');
            $table->string('surah');
            $table->unsignedSmallInteger('ayat_awal');
            $table->unsignedSmallInteger('ayat_akhir');

            // ziyadah = hafalan baru, murajaah = mengulang hafalan lama
            $table->enum('jenis', ['ziyadah', 'murajaah']);

            $table->enum('status', ['lancar', 'kurang_lancar', 'mengulang']);

            $table->text('catatan')->nullable();

            $table->date('tanggal');

            $table->timestamps();

            $table->index(
                ['company_id', 'student_id', 'tanggal'],
                'idx_tahfidz_company_student_tanggal'
            );

            $table->index(
                ['guru_id', 'tanggal'],
                'idx_tahfidz_guru_tanggal'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tahfidz_setorans');
    }
};
