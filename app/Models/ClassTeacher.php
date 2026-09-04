<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassTeacher extends Model
{
    use HasFactory;

    protected $guarded = [];

    // ── Relasi ────────────────────────────────────────────────

    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    // User dengan role = 'guru'
    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
