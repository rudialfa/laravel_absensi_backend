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
        Schema::create('app_policies', function (Blueprint $table) {
            $table->id();

            /*
             * TYPE:
             * privacy_policy    → kebijakan privasi
             * terms_of_service  → syarat & ketentuan
             * refund_policy     → kebijakan refund/pembatalan langganan
             * other             → kebijakan lain-lain
             */
            $table->enum('type', ['privacy_policy', 'terms_of_service', 'refund_policy', 'other'])
                ->default('other');

            $table->string('title');

            // Isi kebijakan (rich text / html dari editor)
            $table->longText('content');

            // Versi kebijakan, contoh: "1.0", "2.1" — supaya histori perubahan tetap tersimpan
            $table->string('version', 20)->default('1.0');

            // Hanya 1 versi per type yang boleh aktif & tampil ke publik/tenant
            $table->boolean('is_active')->default(false);

            $table->timestamp('published_at')->nullable();

            // Superadmin yang mempublikasikan versi ini
            $table->foreignId('published_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['type', 'is_active'], 'idx_policy_type_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_policies');
    }
};
