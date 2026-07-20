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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Siapa yang melakukan aksi (biasanya superadmin)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Aksi singkat, contoh: "suspend_tenant", "verify_invoice", "create_plan"
            $table->string('action');

            // Nama model yang kena aksi, contoh: "Company", "SubscriptionInvoice"
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // Deskripsi bebas untuk ditampilkan di tabel log
            $table->text('description')->nullable();

            // Data tambahan (before/after, payload, dsb)
            $table->json('meta')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id'], 'idx_audit_subject');
            $table->index('action', 'idx_audit_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
