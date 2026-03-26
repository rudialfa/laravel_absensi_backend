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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('chat_conversations')
                ->cascadeOnDelete();

            $table->foreignId('sender_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('type', ['text', 'image', 'video'])->default('text');

            $table->text('body')->nullable();

            $table->foreignId('reply_to_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();

            $table->timestamp('deleted_at_sender')->nullable();
            $table->timestamp('deleted_for_everyone_at')->nullable();
            $table->timestamp('edited_at')->nullable();

            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'idx_msg_conv_time');
            $table->index(['conversation_id', 'sender_id'], 'idx_msg_conv_sender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
