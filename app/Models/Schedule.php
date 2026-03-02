<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = [];

    protected $casts = [
        'start_datetime'       => 'datetime',
        'end_datetime'         => 'datetime',
        'recurrence_end_date'  => 'date',
        'reminder_offsets'     => 'array',
        'location'             => 'array',
        'is_recurring'         => 'boolean',
    ];



    // Jadwal milik siapa (karyawan)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Jadwal dibuat oleh siapa (HR / admin)
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Jadwal milik company mana
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Daftar peserta jadwal
    public function participants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id');
    }

    // Peserta yang sudah accept
    public function acceptedParticipants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id')
            ->where('status', 'accepted');
    }

    // Peserta yang belum respon
    public function pendingParticipants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id')
            ->where('status', 'invited');
    }

    // ==================== SCOPES ====================

    // Filter berdasarkan status
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // Filter jadwal yang akan datang
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
            ->where('start_datetime', '>=', now());
    }

    // Filter berdasarkan company
    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Filter berdasarkan user
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
