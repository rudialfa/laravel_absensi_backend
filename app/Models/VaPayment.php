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

    /**
     * VA ini untuk satu invoice.
     * VaPayment → belongsTo → SubscriptionInvoice
     */
    public function invoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'invoice_id');
    }

    /**
     * VA ini milik satu company.
     * VaPayment → belongsTo → Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Semua log webhook yang masuk untuk VA ini.
     * VaPayment → hasMany → VaPaymentLog
     */
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

    /** VA yang sudah expired (belum dibayar dan waktu sudah lewat) */
    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expired_at', '<', now());
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Bangun virtualAccountNo sesuai format BCA SNAP:
     * partnerServiceId (8 digit, left-pad spasi) + customerNo (maks 20 digit)
     */
    public function getVirtualAccountNo(): string
    {
        return $this->partner_service_id . $this->customer_no;
    }

    /**
     * Tandai VA lunas setelah payment flag dari BCA masuk.
     */
    public function markAsPaid(string $paymentRequestId, string $referenceNo): void
    {
        $this->update([
            'status'             => 'paid',
            'payment_request_id' => $paymentRequestId,
            'bank_reference_no'  => $referenceNo,
            'paid_at'            => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
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
