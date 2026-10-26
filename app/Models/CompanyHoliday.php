<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyHoliday extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Sebelumnya cast 'date' → kolom itu sudah dihapus (diganti start_date & end_date)
    protected $casts = [
        'start_date'       => 'date:Y-m-d',
        'end_date'         => 'date:Y-m-d',
        'reminder_sent_at' => 'datetime',
    ];

    public const TYPE_LABELS = [
        'national' => 'Libur Nasional',
        'company'  => 'Libur Perusahaan',
    ];

    public const DAY_NAMES = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    public const MONTH_SHORT = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ----------------------------------------------------------
    // SCOPES
    // ----------------------------------------------------------

    /** Libur yang mencakup tanggal tertentu */
    public function scopeActiveOn($query, string $date)
    {
        return $query->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date);
    }

    /** Libur yang belum selesai (hari ini & ke depan) */
    public function scopeUpcoming($query)
    {
        return $query->whereDate('end_date', '>=', now()->toDateString());
    }

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    public function getTotalDaysAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) return 0;

        return (int) $this->start_date->diffInDays($this->end_date, true) + 1;
    }

    /** "Senin, 27 Okt 2026" atau "Senin, 27 – Rabu, 29 Okt 2026 (3 hari)" */
    public function getPeriodLabelAttribute(): string
    {
        $s = $this->start_date;
        $e = $this->end_date;

        if (!$s) return '';

        $fmt = fn(Carbon $d, bool $withYear = true) =>
        self::DAY_NAMES[$d->dayOfWeek] . ', ' . $d->day . ' ' . self::MONTH_SHORT[$d->month]
            . ($withYear ? ' ' . $d->year : '');

        if (!$e || $s->isSameDay($e)) {
            return $fmt($s);
        }

        return $fmt($s, $s->year !== $e->year) . ' – ' . $fmt($e) . " ({$this->total_days} hari)";
    }

    /** upcoming | ongoing | past */
    public function getTimeStatusAttribute(): string
    {
        $today = now()->startOfDay();

        if ($this->start_date && $today->lt($this->start_date)) return 'upcoming';
        if ($this->end_date && $today->gt($this->end_date))     return 'past';

        return 'ongoing';
    }

    public function toApiArray(): array
    {
        $tz    = config('app.timezone');
        $today = now()->startOfDay();

        return [
            'id'                => (int) $this->id,
            'company_id'        => (int) $this->company_id,
            'start_date'        => $this->start_date?->format('Y-m-d'),
            'end_date'          => $this->end_date?->format('Y-m-d'),
            'period_label'      => $this->period_label,
            'name'              => $this->name,
            'type'              => $this->type,
            'type_label'        => self::TYPE_LABELS[$this->type] ?? $this->type,
            'note'              => $this->note,
            'total_days'        => $this->total_days,
            'time_status'       => $this->time_status,
            'is_active_today'   => $this->time_status === 'ongoing',
            'days_until'        => $this->start_date && $today->lt($this->start_date)
                ? (int) $today->diffInDays($this->start_date, true)
                : 0,
            'reminder_sent_at'  => $this->reminder_sent_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'created_at'        => $this->created_at
                ? Carbon::parse($this->created_at)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
            'updated_at'        => $this->updated_at
                ? Carbon::parse($this->updated_at)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
        ];
    }
}