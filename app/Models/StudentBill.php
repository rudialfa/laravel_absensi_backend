<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentBill extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'amount'   => 'decimal:2',
    ];

    protected $appends = ['total_paid', 'remaining_amount'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function student()
    {
        return $this->belongsTo(Student::class);
    }
    public function billType()
    {
        return $this->belongsTo(BillType::class);
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->total_paid);
    }

    /**
     * Hitung ulang status berdasarkan total pembayaran — dipanggil setiap
     * kali ada pembayaran baru dicatat, supaya status selalu akurat
     * (bukan cuma di-set manual sekali pas create).
     */
    public function refreshStatus(): void
    {
        $totalPaid = $this->total_paid;

        $status = $totalPaid <= 0
            ? 'belum_lunas'
            : ($totalPaid >= $this->amount ? 'lunas' : 'sebagian');

        $this->update(['status' => $status]);
    }
}
