<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyReport extends Model
{
    use HasFactory;

    protected $guarded = [];

    // score kolom decimal → tanpa cast terkirim sebagai string "85.00"
    protected $casts = [
        'year'         => 'integer',
        'month'        => 'integer',
        'score'        => 'float',
        'approved_at'  => 'datetime',
        'submitted_at' => 'datetime',
    ];

    // Ikut terkirim di semua response (termasuk modul pesantren) — aman, hanya tambahan field
    protected $appends = ['attachment_url', 'period_label', 'status_label'];

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

    public const STATUS_LABELS = [
        'draft'     => 'Draft',
        'submitted' => 'Menunggu review HR',
        'approved'  => 'Disetujui',
        'rejected'  => 'Ditolak',
    ];

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
    // ACCESSORS
    // ----------------------------------------------------------

    /** Sebelumnya lampiran hanya berupa path → Image.network gagal */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment) return null;

        return str_starts_with($this->attachment, 'http')
            ? $this->attachment
            : asset($this->attachment);
    }

    /** Contoh: "Oktober 2026" */
    public function getPeriodLabelAttribute(): ?string
    {
        if (!$this->month || !$this->year) return null;

        return (self::MONTH_NAMES[(int) $this->month] ?? $this->month) . ' ' . $this->year;
    }

    public function getStatusLabelAttribute(): ?string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getAttachmentTypeAttribute(): ?string
    {
        if (!$this->attachment) return null;

        return strtolower(pathinfo($this->attachment, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image';
    }

    // ----------------------------------------------------------
    // FORMAT API (employee & HR)
    // ----------------------------------------------------------

    public function toApiArray(): array
    {
        $tz = config('app.timezone');

        $data = [
            'id'              => $this->id,
            'company_id'      => $this->company_id,
            'user_id'         => $this->user_id,
            'month'           => (int) $this->month,
            'year'            => (int) $this->year,
            'period_label'    => $this->period_label,
            'target'          => $this->target,
            'achievement'     => $this->achievement,
            'problem'         => $this->problem,
            'solution'        => $this->solution,
            'attachment'      => $this->attachment,
            'attachment_url'  => $this->attachment_url,
            'attachment_type' => $this->attachment_type,
            'status'          => $this->status,
            'status_label'    => $this->status_label,
            'score'           => (float) $this->score,
            'submitted_at'    => $this->submitted_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'approved_by'     => $this->relationLoaded('approver') && $this->approver
                ? ['id' => $this->approver->id, 'name' => $this->approver->name]
                : null,
            'approved_at'     => $this->approved_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'approval_note'   => $this->approval_note,
            'can_edit'        => in_array($this->status, ['draft', 'rejected'], true),
            'can_submit'      => $this->status === 'draft',
            'can_delete'      => $this->status === 'draft',
            'created_at'      => $this->created_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'updated_at'      => $this->updated_at?->timezone($tz)->format('Y-m-d H:i:s'),
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
