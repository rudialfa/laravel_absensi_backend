<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillPayment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'paid_at' => 'date',
        'amount'  => 'decimal:2',
    ];

    public function studentBill()
    {
        return $this->belongsTo(StudentBill::class);
    }
    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Nomor kwitansi berikutnya, format KWT-YYYY-000001 (urut per tahun).
     *
     * Diambil dari nomor TERBESAR tahun ini (bukan count()), jadi tetap aman walaupun ada
     * pembayaran yang dihapus. Wajib dipanggil di dalam DB::transaction — baris terakhir
     * dikunci (lockForUpdate) supaya 2 kasir yang mencatat bersamaan tidak dapat nomor sama.
     */
    public static function generateReceiptNumber(): string
    {
        $prefix = 'KWT-' . now()->year . '-';

        $last = static::where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('receipt_number')
            ->lockForUpdate()
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }
}
