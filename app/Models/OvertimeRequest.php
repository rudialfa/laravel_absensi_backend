<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * PENYEBAB TANGGAL MUNDUR 1 HARI:
     * cast 'date' => 'date' membuat JSON berisi "2026-10-20T00:00:00.000000Z".
     * Dengan timezone Asia/Jakarta, tengah malam WIB = 17:00 UTC hari sebelumnya,
     * jadi yang terkirim "2026-10-19T17:00:00.000000Z" → aplikasi menampilkan tanggal 19
     * dan nama hari ikut salah. Dengan 'date:Y-m-d' yang terkirim "2026-10-20".
     */
    protected $casts = [
        'date'        => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'minutes'     => 'integer',
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

    public const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    // ----------------------------------------------------------
    // RELATIONS
    // ----------------------------------------------------------

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    public function getDayNameAttribute(): ?string
    {
        return $this->date ? self::DAY_NAMES[$this->date->dayOfWeek] : null;
    }

    /** Contoh: "Selasa, 20 Oktober 2026" */
    public function getDateLabelAttribute(): ?string
    {
        if (!$this->date) return null;

        return $this->day_name . ', ' . $this->date->day . ' '
            . self::MONTH_NAMES[$this->date->month] . ' ' . $this->date->year;
    }

    /** Lembur lewat tengah malam (misal 20:00 – 02:00) */
    public function getIsOvernightAttribute(): bool
    {
        return $this->start_time && $this->end_time && $this->end_time <= $this->start_time;
    }

    /** Contoh: "2 jam 30 menit" */
    public function getDurationLabelAttribute(): string
    {
        $m = (int) $this->minutes;
        $h = intdiv($m, 60);
        $r = $m % 60;

        return trim(($h ? "{$h} jam " : '') . ($r ? "{$r} menit" : ''));
    }

    public function getEvidenceImageUrlAttribute(): ?string
    {
        if (!$this->evidence_image) return null;

        // Data lama mungkin sudah tersimpan sebagai URL penuh
        return str_starts_with($this->evidence_image, 'http')
            ? $this->evidence_image
            : asset($this->evidence_image);
    }

    /** Format seragam untuk response employee & HR */
    public function toApiArray(): array
    {
        return [
            'id'                 => $this->id,
            'company_id'         => $this->company_id,
            'user_id'            => $this->user_id,
            'attendance_id'      => $this->attendance_id,
            'date'               => $this->date?->toDateString(),
            'day_name'           => $this->day_name,
            'date_label'         => $this->date_label,
            'start_time'         => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time'           => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'is_overnight'       => $this->is_overnight,
            'minutes'            => (int) $this->minutes,
            'duration_label'     => $this->duration_label,
            'reason'             => $this->reason,
            'evidence_image'     => $this->evidence_image,
            'evidence_image_url' => $this->evidence_image_url,
            'status'             => $this->status,
            'approved_by'        => $this->relationLoaded('approver') && $this->approver
                ? ['id' => $this->approver->id, 'name' => $this->approver->name]
                : null,
            'approved_at'        => $this->approved_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'approval_note'      => $this->approval_note,
            'created_at'         => $this->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }
}
