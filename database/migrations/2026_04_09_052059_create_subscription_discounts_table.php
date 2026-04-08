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
        Schema::create('subscription_discounts', function (Blueprint $table) {
            $table->id();
            // Nama promo: "Promo Ramadan", "Diskon Launching"
            $table->string('name');

            // Kode voucher yang diketik user — null berarti diskon otomatis tanpa kode
            $table->string('code', 50)->nullable()->unique();

            // percent = potong sekian persen | fixed = potong nominal Rupiah
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');

            // Nilai: 20 berarti 20% atau Rp 20.000 tergantung discount_type
            $table->decimal('discount_value', 10, 2);

            // Maksimal potongan Rupiah — hanya berlaku saat discount_type = percent
            // Contoh: diskon 20% tapi max Rp 50.000 → isi 50000
            $table->decimal('max_discount_amount', 12, 2)->nullable();

            // Batasi ke 1 paket tertentu — null = berlaku semua paket
            $table->foreignId('plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();

            // Masa berlaku voucher
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();

            // Batas pemakaian total — null = unlimited
            $table->unsignedInteger('max_usage')->nullable();

            // Counter pemakaian
            $table->unsignedInteger('used_count')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['code', 'is_active'],       'idx_discount_code_active');
            $table->index(['valid_from', 'valid_until'], 'idx_discount_validity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_discounts');
    }
};
