<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPermission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date_permission'  => 'date',
        'reviewed_at'      => 'datetime',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // Wali yang mengajukan izin/sakit ini
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // Guru/admin yang approve/reject
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
