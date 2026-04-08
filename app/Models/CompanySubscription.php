<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'started_at'     => 'datetime',
        'expires_at'     => 'datetime',
        'has_used_trial' => 'boolean',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Subscription ini milik satu company.
     * CompanySubscription → belongsTo → Company
     */
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Subscription ini menggunakan satu plan.
     * CompanySubscription → belongsTo → SubscriptionPlan
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Invoice terakhir yang terkait dengan subscription ini.
     * CompanySubscription → belongsTo → SubscriptionInvoice
     */
    public function lastInvoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'last_invoice_id');
    }

    /**
     * Semua invoice yang pernah dibuat untuk subscription ini.
     * CompanySubscription → hasMany → SubscriptionInvoice
     */
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /** Subscription yang masih bisa mengakses fitur */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trial', 'active']);
    }

    /** Subscription yang sudah dikunci */
    public function scopeLocked($query)
    {
        return $query->whereIn('status', ['expired', 'cancelled']);
    }

    /** Subscription yang hampir expired (dalam X hari ke depan) */
    public function scopeExpiringSoon($query, int $days = 3)
    {
        return $query
            ->whereIn('status', ['trial', 'active'])
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Apakah subscription masih aktif?
     * INI yang dipakai Middleware untuk unlock/lock fitur.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['trial', 'active'])
            && Carbon::now()->lessThan($this->expires_at);
    }

    /** Kebalikan isActive — dipakai untuk tampilkan halaman "upgrade" */
    public function isLocked(): bool
    {
        return ! $this->isActive();
    }

    /** Sisa hari masa aktif */
    public function daysRemaining(): int
    {
        if ($this->isLocked()) {
            return 0;
        }

        return (int) Carbon::now()->diffInDays($this->expires_at, false);
    }

    /** Tandai expired (dipanggil scheduler command) */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Aktifkan setelah pembayaran berhasil.
     * Jika masih aktif, perpanjang dari tanggal expires — bukan dari hari ini.
     */
    public function activate(SubscriptionPlan $plan, SubscriptionInvoice $invoice): void
    {
        // Perpanjang dari sisa masa aktif jika belum habis
        $base = $this->isActive()
            ? $this->expires_at->copy()
            : Carbon::now();

        $this->update([
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'started_at'      => $base,
            'expires_at'      => $base->addDays($plan->duration_days),
            'last_invoice_id' => $invoice->id,
        ]);
    }
}
