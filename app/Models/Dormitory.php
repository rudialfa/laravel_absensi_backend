<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dormitory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Pengasuh/penanggung jawab asrama (user biasa, role apapun)
    public function pengasuh()
    {
        return $this->belongsTo(User::class, 'pengasuh_id');
    }

    // Semua kamar di asrama ini
    public function rooms()
    {
        return $this->hasMany(DormitoryRoom::class);
    }

    // Semua santri yang saat ini menghuni asrama ini (lewat kamar-kamarnya,
    // hanya penempatan yang masih aktif / belum keluar)
    public function students()
    {
        return $this->hasManyThrough(
            Student::class,
            DormitoryRoom::class,
            'dormitory_id',  // FK di dormitory_rooms -> dormitories
            'id',            // FK di students -> id (lewat room_assignments, lihat accessor di bawah)
            'id',
            'id'
        );
    }
}
