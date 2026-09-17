<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpdbApplicant extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'birth_date'      => 'date',
        'wants_boarding'  => 'boolean',
    ];

    public function period()
    {
        return $this->belongsTo(PpdbPeriod::class, 'ppdb_period_id');
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function testScores()
    {
        return $this->hasMany(PpdbTestScore::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
