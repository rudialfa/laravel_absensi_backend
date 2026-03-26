<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversation extends Model
{
    use HasFactory;

    protected $guarded = [];

    // ================================
    // RELATIONS
    // ================================

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function lastMessage()
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id')->orderBy('created_at');
    }

    // ================================
    // HELPERS
    // ================================

    /**
     * Ambil "lawan bicara" dari sudut pandang user tertentu
     */
    public function getOtherUser(int $myUserId): User
    {
        return $this->user_one_id === $myUserId
            ? $this->userTwo
            : $this->userOne;
    }

    /**
     * Ambil atau buat percakapan antara dua user dalam satu company.
     * Selalu simpan user_one_id < user_two_id agar tidak duplikat.
     */
    public static function getOrCreate(int $companyId, int $userA, int $userB): self
    {
        $one = min($userA, $userB);
        $two = max($userA, $userB);

        return self::firstOrCreate(
            [
                'company_id'  => $companyId,
                'user_one_id' => $one,
                'user_two_id' => $two,
            ]
        );
    }

    /**
     * Hitung jumlah pesan yang belum dibaca oleh user tertentu
     */
    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('deleted_for_everyone_at')
            ->whereDoesntHave('reads', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->count();
    }
}
