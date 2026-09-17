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
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Wali yang submit atas nama anak — murid tidak login sendiri
            // (sesuai desain Portal Orang Tua)
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();

            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('notes')->nullable(); // catatan wali saat submit

            $table->dateTime('submitted_at');

            // belum_dikoreksi -> guru belum lihat/nilai,
            // dikoreksi -> guru sudah kasih nilai & catatan
            $table->enum('status', ['belum_dikoreksi', 'dikoreksi'])->default('belum_dikoreksi');

            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan_guru')->nullable();

            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at')->nullable();

            $table->timestamps();

            // 1 murid cuma boleh submit 1x per tugas — resubmit = update baris ini
            $table->unique(['assignment_id', 'student_id'], 'uniq_submission_assignment_student');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
