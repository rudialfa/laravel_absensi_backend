<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAchievement extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = ['achieved_at' => 'date'];
    protected $appends = ['certificate_url'];

    public function getCertificateUrlAttribute(): ?string
    {
        if (!$this->certificate_path) return null;
        return Storage::disk('public')->url($this->certificate_path);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function extracurricular()
    {
        return $this->belongsTo(Extracurricular::class);
    }
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
