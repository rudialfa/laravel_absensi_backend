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
        Schema::create('student_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // masuk = siswa pindahan dari sekolah lain ke sini,
            // keluar = siswa pindah dari sini ke sekolah lain
            $table->enum('type', ['masuk', 'keluar']);

            // Diisi kalau type=masuk (dari mana asalnya)
            $table->string('origin_school')->nullable();

            // Diisi kalau type=keluar (ke mana tujuannya)
            $table->string('destination_school')->nullable();

            $table->text('reason')->nullable();
            $table->date('effective_date');
            $table->string('letter_number')->nullable(); // nomor surat pindah

            // Scan/foto surat pindah — nullable
            $table->string('document_path')->nullable();

            $table->foreignId('processed_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'student_id', 'type'], 'idx_mutations_company_student_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_mutations');
    }
};
