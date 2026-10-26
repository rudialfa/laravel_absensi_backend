<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * PENYEBAB JAM TIDAK SESUAI (selisih 7 jam):
     *
     * 1. KELUAR: cast 'datetime' membuat JSON berisi UTC, contoh jadwal 09:00 WIB
     *    terkirim "2026-10-25T02:00:00.000000Z". Aplikasi yang tidak memanggil
     *    .toLocal() menampilkan 02:00.
     *    → Sekarang dikirim waktu lokal tanpa "Z": "2026-10-25 09:00:00".
     *
     * 2. MASUK: kalau aplikasi mengirim "2026-10-25T02:00:00.000Z" (UTC),
     *    Laravel menyimpannya apa adanya sebagai 02:00 (tanpa konversi).
     *    → Sekarang semua input dikonversi dulu ke timezone aplikasi (Asia/Jakarta).
     */
    protected $casts = [
        'start_datetime'      => 'datetime:Y-m-d H:i:s',
        'end_datetime'        => 'datetime:Y-m-d H:i:s',
        'recurrence_end_date' => 'date:Y-m-d',
        'reminder_offsets'    => 'array',
        'location'            => 'array',
        'is_recurring'        => 'boolean',
    ];

    public const TYPE_LABELS = [
        'meeting'   => 'Meeting',
        'task_duty' => 'Tugas',
        'visit'     => 'Kunjungan',
        'training'  => 'Training',
        'other'     => 'Lainnya',
    ];

    public const STATUS_LABELS = [
        'upcoming' => 'Akan datang',
        'done'     => 'Selesai',
        'canceled' => 'Dibatalkan',
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

    /** created_at, updated_at, deleted_at ikut dikirim sebagai waktu lokal */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    // ----------------------------------------------------------
    // MUTATORS — normalisasi input ke timezone aplikasi
    // ----------------------------------------------------------

    public static function toLocal($value): ?string
    {
        if ($value === null || $value === '') return null;

        // String tanpa offset ("2026-10-25 09:00") dianggap sudah WIB.
        // String dengan offset / Z dikonversi ke WIB.
        return Carbon::parse($value)
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    public function setStartDatetimeAttribute($value): void
    {
        $this->attributes['start_datetime'] = self::toLocal($value);
    }

    public function setEndDatetimeAttribute($value): void
    {
        $this->attributes['end_datetime'] = self::toLocal($value);
    }

    // ----------------------------------------------------------
    // RELATIONS
    // ----------------------------------------------------------

    // Jadwal milik siapa (karyawan)
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Jadwal dibuat oleh siapa (HR / admin)
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Jadwal milik company mana
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Daftar peserta jadwal
    public function participants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id');
    }

    // Peserta yang sudah accept
    public function acceptedParticipants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id')
            ->where('status', 'accepted');
    }

    // Peserta yang belum respon
    public function pendingParticipants(): HasMany
    {
        return $this->hasMany(ScheduleParticipant::class, 'schedule_id')
            ->where('status', 'invited');
    }

    // ----------------------------------------------------------
    // SCOPES
    // ----------------------------------------------------------

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming')
            ->where('start_datetime', '>=', now());
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter tanggal yang benar:
     *  - date=2026-10-25                    → semua jadwal di tanggal itu
     *  - start_date=2026-10-01&end_date=... → end_date ikut SEHARIAN PENUH
     *    (sebelumnya jadwal di tanggal end_date jam > 00:00 tidak muncul)
     */
    public function scopeFilterDates($query, $request)
    {
        if ($request->filled('date')) {
            $query->whereDate('start_datetime', $request->date);
        }

        if ($request->filled('start_date')) {
            $query->where('start_datetime', '>=', Carbon::parse($request->start_date)->startOfDay());
        }

        if ($request->filled('end_date')) {
            $query->where('start_datetime', '<=', Carbon::parse($request->end_date)->endOfDay());
        }

        return $query;
    }

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    /** upcoming | ongoing | past | done | canceled — dihitung dari jam sekarang */
    public function getTimeStatusAttribute(): string
    {
        if (in_array($this->status, ['done', 'canceled'], true)) {
            return $this->status;
        }

        $now   = now();
        $start = $this->start_datetime;
        $end   = $this->end_datetime ?? $start?->copy()->addHour();

        if (!$start)            return 'upcoming';
        if ($now->lt($start))   return 'upcoming';
        if ($now->lte($end))    return 'ongoing';

        return 'past';
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

    private static function fmt($value): ?string
    {
        if (!$value) return null;

        return Carbon::parse($value)->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * Format seragam untuk HR & employee.
     * $viewerId diisi untuk employee → menambahkan my_participation & is_owner.
     */
    public function toApiArray(?int $viewerId = null): array
    {
        $start = $this->start_datetime;
        $end   = $this->end_datetime;

        $data = [
            'id'                  => $this->id,
            'company_id'          => $this->company_id,
            'user_id'             => $this->user_id,
            'created_by'          => $this->created_by,
            'title'               => $this->title,
            'description'         => $this->description,
            'type'                => $this->type,
            'type_label'          => self::TYPE_LABELS[$this->type] ?? $this->type,

            // Waktu — semua sudah WIB, tampilkan apa adanya
            'start_datetime'      => $start?->format('Y-m-d H:i:s'),
            'end_datetime'        => $end?->format('Y-m-d H:i:s'),
            'date'                => $start?->format('Y-m-d'),
            'day_name'            => $start ? self::DAY_NAMES[$start->dayOfWeek] : null,
            'date_label'          => $start
                ? self::DAY_NAMES[$start->dayOfWeek] . ', ' . $start->day . ' '
                . self::MONTH_NAMES[$start->month] . ' ' . $start->year
                : null,
            'start_time'          => $start?->format('H:i'),
            'end_time'            => $end?->format('H:i'),
            'time_label'          => $start
                ? $start->format('H:i') . ($end ? ' – ' . $end->format('H:i') : '') . ' WIB'
                : null,
            'duration_minutes'    => $start && $end ? (int) $start->diffInMinutes($end, true) : null,
            'timezone'            => config('app.timezone'),

            'reminder_offsets'    => $this->reminder_offsets ?? [],
            'location'            => $this->location,
            'is_recurring'        => (bool) $this->is_recurring,
            'recurrence_type'     => $this->recurrence_type,
            'recurrence_end_date' => $this->recurrence_end_date?->format('Y-m-d'),
            'status'              => $this->status,
            'status_label'        => self::STATUS_LABELS[$this->status] ?? $this->status,
            'time_status'         => $this->time_status,
            'created_at'          => self::fmt($this->created_at),
            'updated_at'          => self::fmt($this->updated_at),
        ];

        if ($this->relationLoaded('user')) {
            $data['user'] = self::userBrief($this->user);
        }

        if ($this->relationLoaded('creator')) {
            $data['creator'] = self::userBrief($this->creator);
        }

        if ($this->relationLoaded('participants')) {
            $participants = $this->participants;

            $data['participants'] = $participants->map(fn($p) => [
                'id'           => $p->id,
                'user_id'      => $p->user_id,
                'status'       => $p->status,
                'note'         => $p->note,
                'responded_at' => self::fmt($p->responded_at),
                'user'         => $p->relationLoaded('user') ? self::userBrief($p->user) : null,
            ])->values();

            $data['participant_counts'] = [
                'total'    => $participants->count(),
                'invited'  => $participants->where('status', 'invited')->count(),
                'accepted' => $participants->where('status', 'accepted')->count(),
                'declined' => $participants->where('status', 'declined')->count(),
            ];

            if ($viewerId !== null) {
                $mine = $participants->firstWhere('user_id', $viewerId);
                $data['my_participation'] = $mine ? [
                    'id'           => $mine->id,
                    'status'       => $mine->status,
                    'note'         => $mine->note,
                    'responded_at' => self::fmt($mine->responded_at),
                ] : null;
            }
        }

        if ($viewerId !== null) {
            $data['is_owner'] = (int) $this->user_id === $viewerId;
        }

        return $data;
    }
}
