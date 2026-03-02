<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleParticipant extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'responded_at' => 'datetime',
    ];

    // ==================== RELATIONSHIPS ====================

    // Peserta ini milik jadwal mana
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    // Peserta ini adalah user mana
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ==================== SCOPES ====================

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'invited');
    }
}
