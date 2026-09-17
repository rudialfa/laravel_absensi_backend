<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtracurricularMember extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = ['joined_at' => 'date', 'is_active' => 'boolean'];

    public function extracurricular()
    {
        return $this->belongsTo(Extracurricular::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
