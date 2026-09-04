<?php

namespace App\Models;

use App\Enums\School\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StudentAttendance extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date'   => 'date',
        'status' => AttendanceStatus::class,
    ];

    // Otomatis ikut ke response JSON tanpa perlu di-append manual di controller
    protected $appends = ['photo_evidence_url'];

    /**
     * URL publik foto bukti absen — photo_evidence di DB cuma nyimpan
     * path relatif (misal "12_2026-08-20_073015.jpg"), bukan URL lengkap.
     */
    public function getPhotoEvidenceUrlAttribute(): ?string
    {
        if (!$this->photo_evidence) {
            return null;
        }

        return Storage::disk('attendance_photos')->url($this->photo_evidence);
    }

    // ── Relasi ────────────────────────────────────────────────

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Guru yang input absen ini lewat kiosk
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Kiosk asal data absen — nullable kalau diinput manual dari dashboard
    public function device()
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }
}
