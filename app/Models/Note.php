<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public const TYPES = ['warning', 'praise', 'performance', 'absence', 'general'];

    public const TYPE_LABELS = [
        'warning'     => 'Teguran',
        'praise'      => 'Apresiasi',
        'performance' => 'Kinerja',
        'absence'     => 'Kehadiran',
        'general'     => 'Umum',
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

    // ─── Relasi ke karyawan yang diberi catatan ───────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ─── Relasi ke HR yang membuat catatan ───────────────────────────────
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    public function getImageFullUrlAttribute(): ?string
    {
        if (!$this->image_url) return null;

        return str_starts_with($this->image_url, 'http') ? $this->image_url : asset($this->image_url);
    }

    private static function userBrief($u): ?array
    {
        if (!$u) return null;

        return [
            'id'         => $u->id,
            'name'       => $u->name,
            'position'   => $u->position ?? null,
            'department' => $u->department ?? null,
            'image_url'  => !empty($u->image_url) ? asset($u->image_url) : null,
        ];
    }

    /**
     * Format seragam employee & HR.
     * Waktu dikirim WIB tanpa "Z" (sebelumnya created_at UTC → tanggal/jam bergeser).
     */
    public function toApiArray(): array
    {
        $tz      = config('app.timezone');
        $created = $this->created_at ? Carbon::parse($this->created_at)->timezone($tz) : null;

        $data = [
            'id'                 => $this->id,
            'company_id'         => $this->company_id,
            'user_id'            => $this->user_id,
            'created_by'         => $this->created_by,
            'title'              => $this->title,
            'note'               => $this->note,
            'type'               => $this->type,
            'type_label'         => self::TYPE_LABELS[$this->type] ?? $this->type,
            'reason'             => $this->reason,
            'target_achievement' => $this->target_achievement,
            'image_url'          => $this->image_full_url, // URL lengkap, siap Image.network
            'image_path'         => $this->image_url,
            'is_read'            => (bool) $this->is_read,
            'read_at'            => $this->read_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'date'               => $created?->format('Y-m-d'),
            'date_label'         => $created
                ? self::DAY_NAMES[$created->dayOfWeek] . ', ' . $created->day . ' '
                    . self::MONTH_NAMES[$created->month] . ' ' . $created->year
                : null,
            'time'               => $created?->format('H:i'),
            'created_at'         => $created?->format('Y-m-d H:i:s'),
            'updated_at'         => $this->updated_at
                ? Carbon::parse($this->updated_at)->timezone($tz)->format('Y-m-d H:i:s')
                : null,
        ];

        if ($this->relationLoaded('user')) {
            $data['user'] = self::userBrief($this->user);
        }

        if ($this->relationLoaded('creator')) {
            $data['creator'] = self::userBrief($this->creator);
        }

        return $data;
    }
}
