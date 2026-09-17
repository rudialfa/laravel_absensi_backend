<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtracurricularAttendance extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = ['tanggal' => 'date'];

    public function extracurricular()
    {
        return $this->belongsTo(Extracurricular::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
