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
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            // Bisa dibalas oleh tenant (pembuat tiket) ATAU superadmin/staff
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('message');
            $table->string('attachment')->nullable();

            // Balasan internal (catatan antar staff superadmin, tidak terlihat oleh tenant)
            $table->boolean('is_internal_note')->default(false);

            $table->timestamps();

            $table->index(['ticket_id', 'created_at'], 'idx_reply_ticket_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
    }
};
