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
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            // Relasi ke tabel payrools
            $table->foreignId('payroll_id')
                ->constrained('payrools')   // sesuai nama tabel migration kamu
                ->cascadeOnDelete();

            // Nama komponen, contoh: "Tunjangan Makan", "Potongan BPJS", "Bonus Proyek"
            $table->string('name');

            // Jenis komponen: addition = menambah gaji, deduction = mengurangi gaji
            $table->enum('type', ['addition', 'deduction'])->default('addition');

            // Nominal komponen
            $table->decimal('amount', 12, 2)->default(0);

            // Catatan opsional dari HR
            $table->text('note')->nullable();

            $table->timestamps();

            // Index untuk mempercepat query berdasarkan payroll_id
            $table->index('payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_components');
    }
};
