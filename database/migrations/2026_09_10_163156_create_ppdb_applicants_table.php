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
        Schema::create('ppdb_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppdb_period_id')->constrained('ppdb_periods')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Data calon siswa
            $table->string('full_name');
            $table->enum('gender', ['L', 'P']);
            $table->date('birth_date');
            $table->string('previous_school')->nullable();

            // Data orang tua/wali pendaftar (belum punya akun)
            $table->string('parent_name');
            $table->string('parent_phone');
            $table->string('parent_email')->nullable();

            // Minat boarding — cuma relevan kalau company.is_boarding = true
            $table->boolean('wants_boarding')->default(false);

            // pending -> tes -> lulus/tidak_lulus -> (kalau lulus) daftar_ulang -> aktif
            $table->enum('status', ['pending', 'tes', 'lulus', 'tidak_lulus', 'daftar_ulang', 'aktif'])
                ->default('pending');

            $table->text('notes')->nullable();

            // Diisi setelah dikonversi jadi siswa aktif oleh admin
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();

            $table->timestamps();

            $table->index(['ppdb_period_id', 'status'], 'idx_ppdb_applicants_period_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_applicants');
    }
};
