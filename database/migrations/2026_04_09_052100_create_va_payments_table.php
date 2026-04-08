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
        Schema::create('va_payments', function (Blueprint $table) {
            $table->id();
            // 1 invoice = 1 VA (kalau VA expired, buat invoice + VA baru)
            $table->foreignId('invoice_id')
                ->constrained('subscription_invoices')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Bank yang dipakai: bca | mandiri
            $table->enum('bank', ['bca', 'mandiri']);

            // Nomor VA yang ditampilkan ke customer
            $table->string('va_number', 28)->unique();

            // Nama yang muncul di ATM/mBanking
            $table->string('va_name', 100);

            // Nominal yang harus dibayar
            $table->decimal('amount', 12, 2);

            /*
             * STATUS VA:
             * pending   → VA sudah dibuat, menunggu pembayaran
             * paid      → sudah dibayar (konfirmasi dari payment flag bank)
             * expired   → melewati expired_at tanpa pembayaran
             * cancelled → dibatalkan manual
             */
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled'])
                ->default('pending');

            // ── Field spesifik BCA SNAP ──────────────────────────────────

            // partnerServiceId: 8 digit, left-padding spasi (didapat dari BCA)
            $table->string('partner_service_id', 8)->nullable();

            // customerNo: nomor unik per transaksi, maks 20 digit
            // Format bebas, contoh: pakai invoice_id + company_id
            $table->string('customer_no', 20)->nullable();

            // inquiryRequestId: dikirim BCA saat inquiry, dikembalikan saat payment flag
            $table->string('inquiry_request_id', 128)->nullable();

            // paymentRequestId: dikirim BCA saat payment flag (= inquiryRequestId dari payment)
            $table->string('payment_request_id', 128)->nullable();

            // referenceNo dari bank setelah pembayaran berhasil
            $table->string('bank_reference_no', 100)->nullable();

            // ── Waktu ────────────────────────────────────────────────────

            $table->timestamp('created_va_at')->nullable();     // saat VA berhasil dibuat di bank
            $table->timestamp('expired_at')->nullable();        // batas waktu bayar (sama dengan invoice due_at)
            $table->timestamp('paid_at')->nullable();           // konfirmasi bayar dari bank

            // Raw JSON response dari bank saat create VA
            $table->json('bank_response')->nullable();

            $table->timestamps();

            $table->index(['bank', 'status'],          'idx_va_bank_status');
            $table->index(['company_id', 'status'],    'idx_va_company_status');
            $table->index(['status', 'expired_at'],    'idx_va_status_expired');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('va_payments');
    }
};
