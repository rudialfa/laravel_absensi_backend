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
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();

            // Format: INV-20250601-00001
            $table->string('invoice_number', 30)->unique();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('subscription_id')
                ->constrained('company_subscriptions')
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained('subscription_plans')
                ->restrictOnDelete();

            // Diskon yang dipakai — null jika tidak pakai voucher
            $table->foreignId('discount_id')
                ->nullable()
                ->constrained('subscription_discounts')
                ->nullOnDelete();

            // Breakdown harga
            $table->decimal('subtotal', 12, 2);                     // harga plan sebelum diskon
            $table->decimal('discount_amount', 12, 2)->default(0);  // nominal potongan
            $table->decimal('total_amount', 12, 2);                 // yang wajib dibayar

            /*
             * STATUS INVOICE:
             * pending   → belum dibayar, VA aktif menunggu
             * paid      → lunas, dikonfirmasi bank
             * expired   → VA kadaluarsa, belum dibayar sampai due_at
             * cancelled → dibatalkan sebelum dibayar
             */
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])
                ->default('pending');

            $table->timestamp('issued_at');                         // waktu invoice dibuat
            $table->timestamp('due_at');                            // batas bayar (biasanya +24 jam)
            $table->timestamp('paid_at')->nullable();               // waktu konfirmasi dari bank
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status'], 'idx_inv_company_status');
            $table->index(['status', 'due_at'],     'idx_inv_status_due');
        });

        // Setelah table invoice ada, baru daftarkan FK dari company_subscriptions
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->foreign('last_invoice_id')
                ->references('id')
                ->on('subscription_invoices')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
