<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StudentPermission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'date_permission' => 'date',
        'reviewed_at'      => 'datetime',
    ];

    protected $appends = ['attachment_url'];

    /**
     * URL publik lampiran (foto surat dokter, dll) — nullable kalau
     * wali tidak melampirkan apa-apa saat mengajukan izin.
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Wali yang mengajukan izin ini.
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Admin/guru yang me-review (approve/reject) izin ini.
     */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Scope ────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
