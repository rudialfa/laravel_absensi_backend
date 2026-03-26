<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'deleted_at_sender'        => 'datetime',
        'deleted_for_everyone_at'  => 'datetime',
        'edited_at'                => 'datetime',
    ];

    // ================================
    // RELATIONS
    // ================================

    public function conversation()
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function attachments()
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id')->orderBy('sort_order');
    }

    public function reads()
    {
        return $this->hasMany(ChatMessageRead::class, 'message_id');
    }

    // ================================
    // HELPERS
    // ================================

    /**
     * Apakah pesan sudah dibaca oleh user tertentu?
     */
    public function isReadBy(int $userId): bool
    {
        // Pesan sendiri selalu dianggap "sudah dibaca"
        if ($this->sender_id === $userId) {
            return true;
        }

        return $this->reads()->where('user_id', $userId)->exists();
    }

    /**
     * Tandai pesan sebagai sudah dibaca oleh user tertentu
     */
    public function markReadBy(int $userId): void
    {
        if ($this->sender_id === $userId) {
            return; // Tidak perlu mark baca pesan sendiri
        }

        ChatMessageRead::firstOrCreate(
            ['message_id' => $this->id, 'user_id' => $userId],
            ['read_at'    => now()]
        );
    }

    /**
     * Apakah pesan dihapus untuk semua?
     */
    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    /**
     * Scope: hanya pesan yang masih bisa dilihat (belum dihapus untuk semua)
     */
    public function scopeVisible($query)
    {
        return $query->whereNull('deleted_for_everyone_at');
    }
}
