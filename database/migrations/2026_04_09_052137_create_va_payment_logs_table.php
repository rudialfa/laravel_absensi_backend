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
        Schema::create('va_payment_logs', function (Blueprint $table) {
            $table->id();

            // VA yang terkait — nullable karena mungkin VA tidak ditemukan di sistem
            $table->foreignId('va_payment_id')
                ->nullable()
                ->constrained('va_payments')
                ->nullOnDelete();

            $table->enum('bank', ['bca', 'mandiri']);

            /*
             * Jenis event:
             * inquiry        → BCA tanya tagihan ke kita (VA Inquiry callback)
             * payment        → BCA konfirmasi bayar ke kita (VA Payment Flag callback)
             * inquiry_status → kita yang aktif tanya status ke BCA
             */
            $table->enum('event_type', ['inquiry', 'payment', 'inquiry_status']);

            // IP pengirim webhook (untuk validasi keamanan)
            $table->string('ip_address', 45)->nullable();

            // Raw body yang dikirim bank ke kita
            $table->json('request_payload');

            // Response yang kita kirim balik ke bank
            $table->json('response_payload')->nullable();

            // HTTP code yang kita return ke bank
            $table->unsignedSmallInteger('response_http_code')->nullable();

            $table->boolean('is_success')->default(false);
            $table->text('error_message')->nullable();

            // Waktu hit masuk
            $table->timestamp('received_at');

            $table->timestamps();

            $table->index(['va_payment_id', 'event_type'],          'idx_valog_va_event');
            $table->index(['bank', 'event_type', 'received_at'],    'idx_valog_bank_event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('va_payment_logs');
    }
};
