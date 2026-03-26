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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('user_one_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('user_two_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // nullable dulu, FK ke chat_messages ditambah di migration ke-5
            $table->unsignedBigInteger('last_message_id')->nullable();

            $table->timestamps(); // ← hanya sekali

            $table->unique(['company_id', 'user_one_id', 'user_two_id'], 'uniq_conversation');
            $table->index(['company_id', 'user_one_id'], 'idx_conv_user_one');
            $table->index(['company_id', 'user_two_id'], 'idx_conv_user_two');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
