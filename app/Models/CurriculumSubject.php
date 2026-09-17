<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurriculumSubject extends Model
{
    use HasFactory;


    protected $guarded = [];

    // FK ke curricula namanya 'curricula_id', bukan default 'curriculum_id'
    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curricula_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
