<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
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

    /**
     * Cek apakah mapel ini berlaku untuk kelas tertentu.
     * grade_level_min/max null berarti berlaku untuk semua kelas.
     */
    public function appliesTo(int $gradeLevel): bool
    {
        if ($this->grade_level_min === null && $this->grade_level_max === null) {
            return true;
        }

        $min = $this->grade_level_min ?? 1;
        $max = $this->grade_level_max ?? 6;

        return $gradeLevel >= $min && $gradeLevel <= $max;
    }
}
