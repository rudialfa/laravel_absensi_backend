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
        Schema::create('attendance_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Kalau device didedikasikan untuk 1 kelas tertentu.
            // Nullable = device umum, bisa dipakai untuk kelas mana saja
            // (guru pilih kelas dulu di layar kiosk).
            $table->foreignId('class_id')
                ->nullable()
                ->constrained('class_rooms')
                ->nullOnDelete();

            // Label device, contoh: "Kiosk Kelas 1A" atau "Tablet Ruang Guru"
            $table->string('name');

            // Token unik untuk autentikasi device (dipakai sebagai Sanctum
            // personal access token / custom header, bukan token milik user)
            $table->string('device_token')->unique();

            // Identifier fisik device (serial number / IMEI), opsional untuk audit
            $table->string('device_identifier')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();

            // Admin yang mendaftarkan device ini
            $table->foreignId('registered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(
                ['company_id', 'is_active'],
                'idx_devices_company_active'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_devices');
    }
};
