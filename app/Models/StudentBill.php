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

    /**
     * Total yang sudah dibayar. Kalau relasi payments sudah di-load (with('payments')),
     * pakai data itu — tidak query ulang per tagihan (hindari N+1 di daftar tagihan).
     */
    public function getTotalPaidAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->amount - $this->total_paid);
    }

    /**
     * Hitung ulang status berdasarkan total pembayaran — dipanggil setiap
     * kali ada pembayaran baru dicatat / dihapus, supaya status selalu akurat
     * (bukan cuma di-set manual sekali pas create).
     */
    public function refreshStatus(): void
    {
        // Selalu hitung dari database (relasi yang sudah di-load bisa basi)
        $totalPaid = (float) $this->payments()->sum('amount');

        $status = $totalPaid <= 0
            ? 'belum_lunas'
            : ($totalPaid >= (float) $this->amount ? 'lunas' : 'sebagian');

        $this->update(['status' => $status]);
    }
}
