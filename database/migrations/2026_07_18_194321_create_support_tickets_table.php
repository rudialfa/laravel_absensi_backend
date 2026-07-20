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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();

            // Tenant asal tiket — nullable karena bisa saja superadmin sendiri yang buat internal note
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            // User yang mengajukan (HR, ustadz, employee, dst)
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('subject');
            $table->text('message');

            // Kategori tiket, contoh: billing, technical, account, other
            $table->string('category')->default('other');

            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');

            /*
             * STATUS:
             * open        → baru dibuat, belum ditangani
             * in_progress → sedang dikerjakan superadmin/staff
             * resolved    → sudah selesai, menunggu konfirmasi tenant
             * closed      → ditutup (selesai / tidak lanjut)
             */
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open');

            // Staff/superadmin yang menangani
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority'], 'idx_ticket_status_priority');
            $table->index(['company_id', 'status'], 'idx_ticket_company_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
