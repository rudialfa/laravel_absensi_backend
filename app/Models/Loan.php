<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments()
    {
        return $this->hasMany(LoanPayment::class);
    }

    // ── HELPERS ───────────────────────────────────────────────

    // Total yang sudah dibayar
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()->sum('amount_paid');
    }

    // Cek apakah lunas
    public function isPaid(): bool
    {
        return $this->balance <= 0;
    }
}
