<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassRoom extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Nama tabel bukan "class_rooms" (default tebakan Laravel dari nama model),
    // tapi "classes" sesuai migration. Model dinamai ClassRoom karena "Class"
    // adalah reserved word di PHP.

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relasi ────────────────────────────────────────────────

    // Sekolah pemilik kelas ini
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Wali kelas (satu guru penanggung jawab utama)
    public function homeroomTeacher()
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    // Semua murid di kelas ini
    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    // Histori absen yang tercatat di kelas ini
    public function studentAttendances()
    {
        return $this->hasMany(StudentAttendance::class, 'class_id');
    }

    // Baris pivot guru-kelas (dengan role_in_class & subject)
    public function classTeachers()
    {
        return $this->hasMany(ClassTeacher::class, 'class_id');
    }

    // Semua guru yang mengajar di kelas ini (wali kelas + guru mapel)
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'class_teachers', 'class_id', 'user_id')
            ->withPivot(['role_in_class', 'subject'])
            ->withTimestamps();
    }

    // Kiosk/device yang didedikasikan untuk kelas ini
    public function attendanceDevices()
    {
        return $this->hasMany(AttendanceDevice::class, 'class_id');
    }
}
