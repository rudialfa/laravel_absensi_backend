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
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')
                ->constrained('loans')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Nominal
            $table->decimal('amount_expected', 12, 2); // seharusnya bayar (dari monthly_installment)
            $table->decimal('amount_paid', 12, 2);      // actual bayar (boleh partial / lebih)
            $table->decimal('balance_after', 12, 2);    // sisa hutang setelah bayar ini

            // Info pembayaran
            $table->date('payment_date');

            // method: manual = HR input biasa
            //         lump_sum = sekali lunas
            //         payroll  = akan dipakai nanti saat modul payroll aktif
            $table->enum('method', ['manual', 'lump_sum', 'payroll'])->default('manual');

            // payroll_id: nullable dulu, diisi nanti saat modul payroll sudah aktif
            $table->unsignedBigInteger('payroll_id')->nullable();
            $table->foreign('payroll_id')
                ->references('id')
                ->on('payrools')
                ->nullOnDelete();

            $table->text('note')->nullable(); // catatan HR

            // HR yang menginput pembayaran ini
            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['loan_id', 'payment_date']);
            $table->index(['company_id', 'payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
