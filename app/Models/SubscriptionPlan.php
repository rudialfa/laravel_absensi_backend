<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;
    protected $guarded = [];


    protected $casts = [
        'price'     => 'decimal:2',
        'is_free'   => 'boolean',
        'is_active' => 'boolean',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Satu plan bisa dipakai banyak subscription company.
     * SubscriptionPlan → hasMany → CompanySubscription
     */
    public function subscriptions()
    {
        return $this->hasMany(CompanySubscription::class, 'plan_id');
    }

    /**
     * Satu plan bisa punya banyak invoice (histori pembelian).
     * SubscriptionPlan → hasMany → SubscriptionInvoice
     */
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'plan_id');
    }

    /**
     * Satu plan bisa punya banyak diskon/promo khusus.
     * SubscriptionPlan → hasMany → SubscriptionDiscount
     */
    public function discounts()
    {
        return $this->hasMany(SubscriptionDiscount::class, 'plan_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /** Hanya plan yang aktif ditawarkan */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Hanya plan berbayar (bukan trial) */
    public function scopePaid($query)
    {
        return $query->where('is_free', false);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    public function isTrial(): bool
    {
        return $this->is_free && $this->slug === 'trial';
    }
}
