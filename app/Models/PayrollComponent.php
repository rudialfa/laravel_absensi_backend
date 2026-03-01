<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollComponent extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts    = ['amount' => 'decimal:2'];

    public function payroll()
    {
        return $this->belongsTo(Payrool::class, 'payroll_id');
    }
}
