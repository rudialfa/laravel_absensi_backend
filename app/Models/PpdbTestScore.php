<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PpdbTestScore extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = ['score' => 'decimal:2', 'test_date' => 'date'];

    public function applicant()
    {
        return $this->belongsTo(PpdbApplicant::class, 'ppdb_applicant_id');
    }
    public function testedBy()
    {
        return $this->belongsTo(User::class, 'tested_by');
    }
}
