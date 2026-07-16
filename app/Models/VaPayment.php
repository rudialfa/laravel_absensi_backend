<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VaPayment extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'amount'        => 'decimal:2',
        'bank_response' => 'array',
        'created_va_at' => 'datetime',
        'expired_at'    => 'datetime',
        'paid_at'       => 'datetime',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    public function invoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'invoice_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function logs()
    {
        return $this->hasMany(VaPaymentLog::class, 'va_payment_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expired_at', '<', now());
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Bangun virtualAccountNo sesuai format BCA SNAP — tidak dipakai
     * di flow Midtrans, dibiarkan untuk kompatibilitas kalau nanti
     * ada integrasi BCA SNAP langsung juga.
     */
    public function getVirtualAccountNo(): string
    {
        return $this->partner_service_id . $this->customer_no;
    }

    /**
     * ── REVISI ───────────────────────────────────────────────────
     * $paymentRequestId & $referenceNo dijadikan nullable karena Midtrans
     * tidak selalu punya keduanya (beda dengan flow BCA SNAP yang selalu
     * mengirim payment_request_id). Untuk Midtrans, isi $referenceNo
     * dengan transaction_id dari notifikasi.
     */
    public function markAsPaid(?string $paymentRequestId = null, ?string $referenceNo = null): void
    {
        $this->update([
            'status'             => 'paid',
            'payment_request_id' => $paymentRequestId ?? $this->payment_request_id,
            'bank_reference_no'  => $referenceNo ?? $this->bank_reference_no,
            'paid_at'            => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expired_at && now()->gt($this->expired_at));
    }
}
