<?php

namespace App\Models;

use App\Models\ShiftGroup;
use App\Models\UserShiftOverride;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }


    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getAppTypeAttribute()
    {
        return $this->company?->type;
    }

    public function getDashboardKeyAttribute()
    {
        return $this->company?->type . '.' . $this->role;
    }

    public function monthlyReports()
    {
        return $this->hasMany(MonthlyReport::class);
    }

    public function shiftGroups()
    {
        return $this->belongsToMany(ShiftGroup::class, 'shift_group_users')
            ->withPivot(['start_date', 'end_date'])
            ->withTimestamps();
    }

    public function shiftOverrides()
    {
        return $this->hasMany(UserShiftOverride::class);
    }
}
