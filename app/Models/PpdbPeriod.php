<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpdbPeriod extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'registration_start' => 'date',
        'registration_end'   => 'date',
        'is_active'          => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function applicants()
    {
        return $this->hasMany(PpdbApplicant::class);
    }

    public function isOpen(): bool
    {
        $today = now()->toDateString();
        return $this->is_active
            && $today >= $this->registration_start->toDateString()
            && $today <= $this->registration_end->toDateString();
    }
}
