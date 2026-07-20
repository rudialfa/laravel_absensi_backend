<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppPolicy extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function publishedBy()
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
