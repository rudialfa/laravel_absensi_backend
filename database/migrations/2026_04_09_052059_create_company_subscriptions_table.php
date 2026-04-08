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
        Schema::create('company_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained('subscription_plans')
                ->restrictOnDelete();

            /*
             * STATUS:
             * trial     → sedang menikmati trial 7 hari gratis
             * active    → langganan berbayar aktif
             * grace     → 3 hari toleransi setelah expired sebelum dikunci total
             * expired   → masa aktif habis, semua fitur dikunci
             * cancelled → dibatalkan manual oleh company/admin
             */
            $table->enum('status', ['trial', 'active', 'grace', 'expired', 'cancelled'])
                ->default('trial');

            // Kapan langganan ini dimulai
            $table->timestamp('started_at');

            // Kapan masa aktif berakhir — INI yang dicek untuk lock/unlock fitur
            $table->timestamp('expires_at');

            // Guard: 1 company hanya boleh trial 1 kali seumur hidup
            $table->boolean('has_used_trial')->default(false);

            // FK ke invoice terakhir (referensi cepat, FK ditambah setelah table invoice dibuat)
            $table->unsignedBigInteger('last_invoice_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // 1 company hanya punya 1 baris subscription (upsert, bukan insert baru)
            $table->unique('company_id', 'uniq_company_subscription');

            $table->index(['status', 'expires_at'], 'idx_sub_status_expires');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_subscriptions');
    }
};
