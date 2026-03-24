<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'date'                => 'date',
        'approved_overtime'   => 'boolean',
        'face_verified'       => 'boolean',
        'late_minutes'        => 'integer',
        'early_leave_minutes' => 'integer',
        'overtime_minutes'    => 'integer',
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
