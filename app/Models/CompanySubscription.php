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

    // Masa tenggang setelah expires_at sebelum status jadi "expired"
    public const GRACE_PERIOD_DAYS = 3;

    // ============================================================
    // RELATIONS
    // ============================================================

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function lastInvoice()
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'last_invoice_id');
    }

    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /** Subscription yang masih "penuh aktif" (belum masuk grace) */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['trial', 'active']);
    }

    /** Subscription yang sudah benar-benar dikunci (bukan grace) */
    public function scopeLocked($query)
    {
        return $query->whereIn('status', ['expired', 'cancelled']);
    }

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
     * Apakah subscription masih boleh akses fitur?
     * trial/active → aktif selama belum lewat expires_at.
     * grace        → tetap dianggap aktif sampai expires_at + GRACE_PERIOD_DAYS.
     * INI yang dipakai untuk unlock/lock fitur (bukan scopeActive di atas,
     * yang cuma buat query/reporting "subscription murni aktif").
     */
    public function isActive(): bool
    {
        if ($this->status === 'cancelled') {
            return false;
        }

        if (in_array($this->status, ['trial', 'active'])) {
            return Carbon::now()->lessThan($this->expires_at);
        }

        if ($this->status === 'grace') {
            return Carbon::now()->lessThan(
                $this->expires_at->copy()->addDays(self::GRACE_PERIOD_DAYS)
            );
        }

        return false; // expired
    }

    public function isLocked(): bool
    {
        return ! $this->isActive();
    }

    public function isGrace(): bool
    {
        return $this->status === 'grace';
    }

    /**
     * Sisa hari. Untuk trial/active → sampai expires_at.
     * Untuk grace → sampai batas akhir masa tenggang (expires_at + GRACE_PERIOD_DAYS).
     */
    public function daysRemaining(): int
    {
        if (in_array($this->status, ['expired', 'cancelled'])) {
            return 0;
        }

        $target = $this->status === 'grace'
            ? $this->expires_at->copy()->addDays(self::GRACE_PERIOD_DAYS)
            : $this->expires_at;

        return max(0, (int) Carbon::now()->diffInDays($target, false));
    }

    /**
     * Sinkronkan status di DB berdasarkan expires_at saat ini:
     * trial/active yang lewat expires_at → grace → (lewat grace period) → expired.
     * Dipanggil setiap kali status dibaca (self-healing), jadi tidak
     * bergantung 100% ke scheduler harian.
     */
    public function syncStatus(): self
    {
        if (in_array($this->status, ['expired', 'cancelled'])) {
            return $this;
        }

        if (Carbon::now()->greaterThanOrEqualTo($this->expires_at)) {
            $graceUntil = $this->expires_at->copy()->addDays(self::GRACE_PERIOD_DAYS);
            $newStatus  = Carbon::now()->lessThan($graceUntil) ? 'grace' : 'expired';

            if ($newStatus !== $this->status) {
                $this->update(['status' => $newStatus]);
            }
        }

        return $this;
    }

    /** Tandai expired (dipanggil scheduler command) */
    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Aktifkan setelah pembayaran berhasil.
     * Jika masih aktif (termasuk grace), perpanjang dari expires_at lama —
     * bukan dari hari ini, supaya tidak rugi sisa hari yang belum kepakai.
     */
    public function activate(SubscriptionPlan $plan, SubscriptionInvoice $invoice): void
    {
        $base = $this->isActive()
            ? $this->expires_at->copy()
            : Carbon::now();

        $this->update([
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'started_at'      => $this->started_at ?? Carbon::now(),
            'expires_at'      => $base->addDays($plan->duration_days),
            'last_invoice_id' => $invoice->id,
        ]);
    }
}
