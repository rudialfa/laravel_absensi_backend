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
        Schema::create('student_bills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('bill_type_id')->constrained('bill_types')->cascadeOnDelete();

            $table->string('title'); // contoh: "SPP Januari 2027", "Uang Buku Semester Genap"
            $table->decimal('amount', 12, 2);
            $table->date('due_date');

            // Bulan/tahun ini nullable, cuma dipakai kalau bill_type periode=bulanan,
            // dipakai buat cegah generate SPP dobel untuk bulan yang sama
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->unsignedSmallInteger('period_year')->nullable();

            // Denormalisasi status supaya query rekap cepat, tapi tetap dihitung
            // ulang otomatis setiap kali ada pembayaran baru (lihat StudentBill model)
            $table->enum('status', ['belum_lunas', 'sebagian', 'lunas'])->default('belum_lunas');

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'student_id', 'status'], 'idx_bills_company_student_status');

            // Cegah generate SPP dobel untuk siswa yang sama di bulan yang sama
            $table->unique(
                ['student_id', 'bill_type_id', 'period_month', 'period_year'],
                'uniq_bill_student_type_period'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_bills');
    }
};
