<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;
    protected $guarded = [];

    // ─── Relasi ke karyawan yang diberi catatan ───────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Relasi ke HR yang membuat catatan ───────────────────────────────
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
