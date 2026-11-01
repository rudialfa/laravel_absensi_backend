<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat notifikasi per user (kotak masuk notifikasi di aplikasi).
     * Nama tabel "app_notifications" supaya tidak bentrok dengan tabel
     * "notifications" bawaan Laravel.
     */
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->cascadeOnDelete();

            // Penerima
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Contoh: leave.approved, chat.message, attendance.checkin_reminder
            $table->string('type', 60);

            // Pengelompokan untuk ikon & filter di aplikasi:
            // chat | approval | reminder | attendance | schedule | holiday |
            // report | payroll | school | subscription | system
            $table->string('category', 30)->default('system');

            $table->string('title');
            $table->text('body');

            // Data tambahan untuk navigasi di aplikasi (id pengajuan, dsb)
            $table->json('data')->nullable();

            // Halaman tujuan saat notifikasi diklik, contoh: "leave_detail"
            $table->string('action', 60)->nullable();

            // Objek yang dirujuk, contoh: Leaves #12
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamp('read_at')->nullable();

            // Status push FCM: sent | failed | no_token | skipped
            $table->string('push_status', 20)->nullable();
            $table->timestamp('push_sent_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'idx_appnotif_user_read');
            $table->index(['user_id', 'created_at'], 'idx_appnotif_user_created');
            $table->index(['reference_type', 'reference_id'], 'idx_appnotif_reference');
            $table->index(['type', 'created_at'], 'idx_appnotif_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
