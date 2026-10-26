<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * 'date' => 'date' membuat JSON "2026-10-24T17:00:00.000000Z" untuk laporan
     * tanggal 25 (tengah malam WIB = 17:00 UTC hari sebelumnya) → tanggal mundur 1 hari.
     * Dengan 'date:Y-m-d' yang terkirim "2026-10-25".
     */
    protected $casts = [
        'date'        => 'date:Y-m-d',
        'is_achieved' => 'boolean',
    ];

    public const DAY_NAMES = [
        0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu',
        4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu',
    ];

    public const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope filter per bulan
    public function scopeByMonth($query, $year, $month)
    {
        return $query->whereYear('date', $year)
            ->whereMonth('date', $month);
    }

    public function scopeAchieved($query)
    {
        return $query->where('is_achieved', true);
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    /** pending_evening | achieved | not_achieved */
    public function getProgressStatusAttribute(): string
    {
        if ($this->achievement === null) return 'pending_evening';

        return $this->is_achieved ? 'achieved' : 'not_achieved';
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment) return null;

        return str_starts_with($this->attachment, 'http') ? $this->attachment : asset($this->attachment);
    }

    /**
     * Sore boleh diisi / diedit sampai akhir HARI BERIKUTNYA
     * (kalau lupa isi sore, masih bisa besok).
     */
    public function getCanUpdateAttribute(): bool
    {
        if (!$this->date) return false;

        return now()->lte($this->date->copy()->addDay()->endOfDay());
    }

    public function toApiArray(): array
    {
        $tz = config('app.timezone');

        $data = [
            'id'                  => $this->id,
            'company_id'          => $this->company_id,
            'user_id'             => $this->user_id,
            'date'                => $this->date?->format('Y-m-d'),
            'day_name'            => $this->date ? self::DAY_NAMES[$this->date->dayOfWeek] : null,
            'date_label'          => $this->date
                ? self::DAY_NAMES[$this->date->dayOfWeek] . ', ' . $this->date->day . ' '
                    . self::MONTH_NAMES[$this->date->month] . ' ' . $this->date->year
                : null,
            'target'              => $this->target,
            'achievement'         => $this->achievement,
            'is_achieved'         => (bool) $this->is_achieved,
            'reason_not_achieved' => $this->reason_not_achieved,
            'progress_status'     => $this->progress_status,
            'attachment'          => $this->attachment,
            'attachment_url'      => $this->attachment_url,
            'can_update'          => $this->can_update,
            'submitted_morning_at'=> $this->created_at
                ? Carbon::parse($this->created_at)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
            'updated_at'          => $this->updated_at
                ? Carbon::parse($this->updated_at)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
        ];

        if ($this->relationLoaded('user') && $this->user) {
            $data['user'] = [
                'id'         => $this->user->id,
                'name'       => $this->user->name,
                'position'   => $this->user->position,
                'department' => $this->user->department,
                'image_url'  => $this->user->image_url ? asset($this->user->image_url) : null,
            ];
        }

        return $data;
    }
}
