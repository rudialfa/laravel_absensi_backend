<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id');
    }

    /** Balasan yang boleh dilihat pemilik tiket (bukan catatan internal). */
    public function publicReplies()
    {
        return $this->hasMany(SupportTicketReply::class, 'ticket_id')
            ->where('is_internal_note', false);
    }

    /** Balasan publik terakhir (untuk daftar tiket di aplikasi). */
    public function latestPublicReply()
    {
        return $this->hasOne(SupportTicketReply::class, 'ticket_id')
            ->where('is_internal_note', false)
            ->latestOfMany();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }
}