<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGuardian extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_primary'             => 'boolean',
        'can_submit_permission'  => 'boolean',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    // User dengan role = 'wali'
    public function guardian()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
