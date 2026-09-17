<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MutabaahRecord extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tanggal'       => 'date',
        'dzikir_pagi'   => 'boolean',
        'dzikir_petang' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
