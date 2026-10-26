<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;
    protected $guarded = [];

    public const TYPE_TIDAK_MASUK      = 'tidak_masuk';
    public const TYPE_DATANG_TERLAMBAT = 'datang_terlambat';
    public const TYPE_PULANG_CEPAT     = 'pulang_cepat';
    public const TYPE_KELUAR_KANTOR    = 'keluar_kantor';
    public const TYPE_DINAS_LUAR       = 'dinas_luar';

    public const TYPES = [
        self::TYPE_TIDAK_MASUK,
        self::TYPE_DATANG_TERLAMBAT,
        self::TYPE_PULANG_CEPAT,
        self::TYPE_KELUAR_KANTOR,
        self::TYPE_DINAS_LUAR,
    ];

    public const TYPE_LABELS = [
        self::TYPE_TIDAK_MASUK      => 'Izin tidak masuk',
        self::TYPE_DATANG_TERLAMBAT => 'Izin datang terlambat',
        self::TYPE_PULANG_CEPAT     => 'Izin pulang cepat',
        self::TYPE_KELUAR_KANTOR    => 'Izin keluar kantor',
        self::TYPE_DINAS_LUAR       => 'Dinas luar',
    ];

    // PENTING: tanpa cast ini, is_approved dari MySQL bernilai 1/0 (int),
    // sehingga pengecekan `=== true` selalu gagal.
    protected $casts = [
        'date_permission' => 'date',
        'end_date'        => 'date',
        'is_approved'     => 'boolean',
        'approved_at'     => 'datetime',
    ];

    protected $appends = ['status_label', 'type_label'];

    // ----------------------------------------------------------
    // RELATIONS
    // ----------------------------------------------------------

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ----------------------------------------------------------
    // SCOPES
    // ----------------------------------------------------------

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Izin yang berlaku pada tanggal tertentu
     * (date_permission <= date <= COALESCE(end_date, date_permission)).
     */
    public function scopeActiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->whereDate('date_permission', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereNull('end_date')->whereDate('date_permission', $date);
                })->orWhereDate('end_date', '>=', $date);
            });
    }

    // ----------------------------------------------------------
    // ACCESSORS
    // ----------------------------------------------------------

    public function getStatusLabelAttribute(): string
    {
        return match ($this->is_approved) {
            true    => 'approved',
            false   => 'rejected',
            default => 'pending',
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type ?? self::TYPE_TIDAK_MASUK] ?? 'Izin';
    }
}
