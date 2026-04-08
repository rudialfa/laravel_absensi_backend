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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            // Nama tampil ke user
            $table->string('name');

            // Slug unik untuk referensi di kode: trial | monthly | biannual | yearly
            $table->string('slug')->unique();

            $table->text('description')->nullable();

            // Durasi dalam hari: 7 | 30 | 180 | 365
            $table->unsignedSmallInteger('duration_days');

            // Harga normal dalam Rupiah (0 untuk paket trial/gratis)
            $table->decimal('price', 12, 2)->default(0);

            // True = gratis, tidak perlu bayar
            $table->boolean('is_free')->default(false);

            $table->boolean('is_active')->default(true);

            // Urutan tampil di halaman pricing
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
