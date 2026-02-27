<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShiftGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        // pivot: shift_group_users
        return $this->belongsToMany(User::class, 'shift_group_users')
            ->withPivot(['start_date', 'end_date'])
            ->withTimestamps();
    }

    public function assignments()
    {
        return $this->hasMany(ShiftGroupAssignment::class, 'shift_group_id');
    }
}
