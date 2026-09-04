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
        Schema::create('student_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // Wali yang mengajukan
            $table->foreignId('submitted_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('date_permission');

            $table->enum('type', ['izin', 'sakit'])->default('izin');

            $table->text('reason');

            // Surat dokter / lampiran pendukung, opsional
            $table->string('attachment')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');

            // Guru/admin yang memproses pengajuan ini
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['student_id', 'date_permission'],
                'idx_student_permission_date'
            );

            $table->index('status', 'idx_student_permission_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_permissions');
    }
};
