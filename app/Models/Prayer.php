<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prayer extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** Jam sholat "HH:MM" (kolom TIME tersimpan "HH:MM:SS") */
    public function hm(string $field): ?string
    {
        return $this->{$field} ? substr((string) $this->{$field}, 0, 5) : null;
    }
}