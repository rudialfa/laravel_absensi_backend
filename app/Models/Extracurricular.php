<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Extracurricular extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function pembina()
    {
        return $this->belongsTo(User::class, 'pembina_id');
    }
    public function members()
    {
        return $this->hasMany(ExtracurricularMember::class);
    }
    public function activeMembers()
    {
        return $this->hasMany(ExtracurricularMember::class)->where('is_active', true);
    }
    public function attendances()
    {
        return $this->hasMany(ExtracurricularAttendance::class);
    }
}
