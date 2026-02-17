<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'reminder_offsets' => 'array',
        'location'         => 'array',
        'start_datetime'   => 'datetime',
        'is_task_duty'     => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
