<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DormitoryRoom extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function dormitory()
    {
        return $this->belongsTo(Dormitory::class);
    }

    // Seluruh histori penempatan di kamar ini (termasuk yang sudah keluar)
    public function assignments()
    {
        return $this->hasMany(RoomAssignment::class, 'room_id');
    }

    // Penempatan yang masih aktif saat ini (belum ada moved_out_at)
    public function activeAssignments()
    {
        return $this->hasMany(RoomAssignment::class, 'room_id')
            ->whereNull('moved_out_at');
    }

    // Jumlah penghuni aktif sekarang — dipakai untuk cek kapasitas
    public function getOccupancyCountAttribute(): int
    {
        return $this->activeAssignments()->count();
    }

    // Sisa slot yang masih bisa diisi
    public function getAvailableSlotsAttribute(): int
    {
        return max(0, $this->capacity - $this->occupancy_count);
    }
}
