<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SubscriptionDiscount extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'discount_value'      => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'valid_from'          => 'datetime',
        'valid_until'         => 'datetime',
        'is_active'           => 'boolean',
    ];

    // ============================================================
    // RELATIONS
    // ============================================================

    /**
     * Diskon bisa dikaitkan ke 1 plan tertentu (opsional).
     * SubscriptionDiscount → belongsTo → SubscriptionPlan
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Satu kode diskon bisa dipakai di banyak invoice.
     * SubscriptionDiscount → hasMany → SubscriptionInvoice
     */
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class, 'discount_id');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    /**
     * Diskon yang masih valid saat ini.
     */
    public function scopeValid($query)
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now))
            ->where(fn($q) => $q->whereNull('max_usage')->orWhereRaw('used_count < max_usage'));
    }

    // ============================================================
    // HELPERS
    // ============================================================

    /**
     * Hitung nominal potongan dari harga asli.
     *
     * @param  float $price  Harga sebelum diskon
     * @return float         Nominal potongan (bukan harga akhir)
     */
    public function calculateDiscount(float $price): float
    {
        if ($this->discount_type === 'percent') {
            $cut = $price * ((float) $this->discount_value / 100);

            // Terapkan batas maksimal jika ada
            if ($this->max_discount_amount) {
                $cut = min($cut, (float) $this->max_discount_amount);
            }

            return round($cut, 2);
        }

        // fixed — tidak boleh melebihi harga asli
        return min((float) $this->discount_value, $price);
    }

    public function isValid(): bool
    {
        $now = Carbon::now();

        if (! $this->is_active)                                      return false;
        if ($this->valid_from   && $now->lt($this->valid_from))      return false;
        if ($this->valid_until  && $now->gt($this->valid_until))     return false;
        if ($this->max_usage    && $this->used_count >= $this->max_usage) return false;

        return true;
    }

    /** Tambah counter pemakaian */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
