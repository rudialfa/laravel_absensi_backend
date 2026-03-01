<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'amount_expected' => 'decimal:2',
        'amount_paid'     => 'decimal:2',
        'balance_after'   => 'decimal:2',
        'payment_date'    => 'date',
    ];

    // ── RELATIONS ─────────────────────────────────────────────

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // payroll_id akan disambungkan nanti saat modul payroll aktif
    // public function payroll()
    // {
    //     return $this->belongsTo(Payrool::class, 'payroll_id');
    // }
}
