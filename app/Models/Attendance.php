<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'date'                  => 'date',
        'approved_overtime'     => 'boolean',
        'face_verified'         => 'boolean',
        'is_outside_radius_in'  => 'boolean',
        'is_outside_radius_out' => 'boolean',
        'late_minutes'          => 'integer',
        'early_leave_minutes'   => 'integer',
        'overtime_minutes'      => 'integer',
        'distance_in_m'         => 'integer',
        'distance_out_m'        => 'integer',
    ];

    // ----------------------------------------------------------
    // RELATIONS
    // ----------------------------------------------------------

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    // ----------------------------------------------------------
    // ACCESSORS
    // ----------------------------------------------------------

    public function getPhotoInUrlAttribute(): ?string
    {
        return $this->photo_in ? asset($this->photo_in) : null;
    }

    public function getPhotoOutUrlAttribute(): ?string
    {
        return $this->photo_out ? asset($this->photo_out) : null;
    }

    // ----------------------------------------------------------
    // SCOPES
    // ----------------------------------------------------------

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('date', now()->toDateString());
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }
}
