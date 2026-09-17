<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomAssignment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'moved_in_at'  => 'date',
        'moved_out_at' => 'date',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function room()
    {
        return $this->belongsTo(DormitoryRoom::class, 'room_id');
    }

    // Admin/guru yang melakukan penempatan
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ── Scope ────────────────────────────────────────────────

    // Cuma penempatan yang masih berjalan (belum keluar/pindah)
    public function scopeActive($query)
    {
        return $query->whereNull('moved_out_at');
    }
}
