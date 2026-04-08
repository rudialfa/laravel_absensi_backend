<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'issued_at'       => 'datetime',
        'due_at'          => 'datetime',
        'paid_at'         => 'datetime',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Invoice ini milik satu company.
     * SubscriptionInvoice → belongsTo → Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Invoice ini terkait satu subscription.
     * SubscriptionInvoice → belongsTo → CompanySubscription
     */
    public function subscription()
    {
        return $this->belongsTo(CompanySubscription::class, 'subscription_id');
    }

    /**
     * Invoice ini untuk satu plan tertentu.
     * SubscriptionInvoice → belongsTo → SubscriptionPlan
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Diskon yang digunakan pada invoice ini (opsional).
     * SubscriptionInvoice → belongsTo → SubscriptionDiscount
     */
    public function discount()
    {
        return $this->belongsTo(SubscriptionDiscount::class, 'discount_id');
    }

    /**
     * Invoice ini punya satu VA payment.
     * SubscriptionInvoice → hasOne → VaPayment
     */
    public function vaPayment()
    {
        return $this->hasOne(VaPayment::class, 'invoice_id');
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

    /** Invoice yang sudah lewat due_at tapi belum dibayar */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_at', '<', now());
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Generate nomor invoice unik per hari.
     * Format: INV-20250601-00001
     */
    public static function generateNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ymd') . '-';

        $last = static::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status'  => 'paid',
            'paid_at' => now(),
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
}
