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
        Schema::create('boarding_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('student_id')
                ->constrained('students')
                ->cascadeOnDelete();

            // izin_pulang = pulang ke rumah (biasanya akhir pekan/libur)
            // izin_keluar = keluar sementara (urusan tertentu, hari yang sama)
            $table->enum('jenis', ['izin_pulang', 'izin_keluar']);

            $table->text('alasan');

            $table->date('tanggal_keluar');
            $table->date('tanggal_kembali_rencana');
            $table->date('tanggal_kembali_aktual')->nullable();

            $table->string('nama_penjemput')->nullable();
            $table->string('hubungan_penjemput')->nullable();
            $table->string('kontak_penjemput')->nullable();

            // pending -> approved/rejected -> (kalau approved) sudah_kembali
            $table->enum('status', ['pending', 'approved', 'rejected', 'sudah_kembali'])
                ->default('pending');

            // Wali yang mengajukan
            $table->foreignId('submitted_by')
                ->constrained('users')
                ->cascadeOnDelete();

            // Admin/pengasuh yang approve/reject
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('catatan_review')->nullable();

            $table->timestamps();

            $table->index(
                ['company_id', 'student_id', 'status'],
                'idx_boarding_perm_company_student_status'
            );

            $table->index(
                ['company_id', 'status', 'tanggal_keluar'],
                'idx_boarding_perm_company_status_tanggal'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boarding_permissions');
    }
};
