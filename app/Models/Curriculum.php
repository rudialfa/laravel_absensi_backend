<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Curriculum extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // FK di curriculum_subjects namanya 'curricula_id', bukan default 'curriculum_id'
    public function curriculumSubjects()
    {
        return $this->hasMany(CurriculumSubject::class, 'curricula_id');
    }

    /**
     * Semua mapel untuk 1 tingkat kelas tertentu di kurikulum ini,
     * lengkap dengan jam per minggunya.
     */
    public function subjectsForGrade(int $gradeLevel)
    {
        return $this->curriculumSubjects()
            ->where('grade_level', $gradeLevel)
            ->with('subject');
    }
}
