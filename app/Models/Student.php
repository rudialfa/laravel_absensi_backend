<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'birth_date'   => 'date',
        'enrolled_at'  => 'date',
        'is_boarding'  => 'boolean',
        'is_active'    => 'boolean',
    ];

    // ── Relasi ────────────────────────────────────────────────

    // Sekolah tempat murid terdaftar
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Kelas murid saat ini
    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    // Seluruh histori absen murid ini
    public function attendances()
    {
        return $this->hasMany(StudentAttendance::class);
    }

    // Absen hari ini saja — dipakai layar kiosk & dashboard wali
    public function todayAttendance()
    {
        return $this->hasOne(StudentAttendance::class)->whereDate('date', now()->toDateString());
    }

    // Seluruh pengajuan izin/sakit murid ini
    public function permissions()
    {
        return $this->hasMany(StudentPermission::class);
    }

    // Baris pivot wali-murid (dengan relationship, is_primary, dst)
    public function studentGuardians()
    {
        return $this->hasMany(StudentGuardian::class);
    }

    // Semua akun wali/orang tua yang terhubung ke murid ini
    public function guardians()
    {
        return $this->belongsToMany(User::class, 'student_guardians', 'student_id', 'user_id')
            ->withPivot(['relationship', 'is_primary', 'can_submit_permission'])
            ->withTimestamps();
    }
}
