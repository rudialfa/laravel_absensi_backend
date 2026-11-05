<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengumuman / notifikasi massal dari superadmin ke pengguna tenant.
     * Isi notifikasinya tetap disimpan per penerima di app_notifications
     * (reference_type = 'BroadcastNotification', reference_id = id tabel ini),
     * sehingga statistik terkirim & dibaca bisa dihitung dari sana.
     */
    public function up(): void
    {
        Schema::create('broadcast_notifications', function (Blueprint $table) {
            $table->id();

            $table->string('title', 150);
            $table->text('body');

            // Kategori ikon di aplikasi (system, subscription, school, dll)
            $table->string('category', 30)->default('system');

            // Opsional: halaman tujuan di aplikasi & link luar
            $table->string('action', 60)->nullable();
            $table->string('url')->nullable();

            /*
             * Target penerima:
             *  all       → semua pengguna tenant
             *  types     → jenis tenant tertentu (company / school / pesantren)
             *  companies → tenant tertentu
             *  users     → pengguna tertentu (daftar id)
             */
            $table->string('target', 20)->default('all');
            $table->json('company_types')->nullable();
            $table->json('company_ids')->nullable();
            $table->json('user_ids')->nullable();
            // Kosong = semua role
            $table->json('roles')->nullable();
            // Lewati tenant yang sedang disuspend
            $table->boolean('only_active_tenants')->default(true);

            // draft | scheduled | sending | sent | failed | canceled
            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // Hasil pengiriman
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('stored_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('no_token_count')->default(0);
            $table->text('error')->nullable();

            // Ditarik dari kotak masuk pengguna
            $table->timestamp('retracted_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at'], 'idx_broadcast_status_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_notifications');
    }
};
