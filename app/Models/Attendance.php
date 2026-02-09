<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function marker()
    {
        return $this->belongsTo(\App\Models\User::class, 'marked_by');
    }


    protected $casts = [
        'approved_overtime' => 'boolean',
        'date' => 'date',
    ];
}
