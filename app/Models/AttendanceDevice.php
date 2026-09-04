<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_seen_at'  => 'datetime',
    ];

    // Token device tidak boleh ikut ter-serialize ke response API sembarangan
    protected $hidden = [
        'device_token',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Kelas yang jadi "rumah" device ini — nullable kalau device umum
    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    // Admin yang mendaftarkan device ini
    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    // Riwayat absen yang masuk lewat device ini
    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class, 'device_id');
    }
}
