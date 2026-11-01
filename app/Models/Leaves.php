<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Leaves extends Model
{
    use HasFactory;

    protected $guarded = [];

    // 'date' → 'date:Y-m-d' supaya tanggal tidak mundur 1 hari di aplikasi
    protected $casts = [
        'start_date'  => 'date:Y-m-d',
        'end_date'    => 'date:Y-m-d',
        'approved_at' => 'datetime',
    ];

    public const TYPES = ['annual', 'sick', 'maternity', 'important', 'other'];

    public const TYPE_LABELS = [
        'annual'    => 'Cuti tahunan',
        'sick'      => 'Cuti sakit',
        'maternity' => 'Cuti melahirkan',
        'important' => 'Cuti alasan penting',
        'other'     => 'Cuti lainnya',
    ];

    public const STATUS_LABELS = [
        'pending'  => 'Menunggu persetujuan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'canceled' => 'Dibatalkan',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? 'Cuti';
    }

    public function getTotalDaysAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) return 0;

        return (int) $this->start_date->diffInDays($this->end_date, true) + 1;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (!$this->attachment) return null;

        return str_starts_with($this->attachment, 'http') ? $this->attachment : asset($this->attachment);
    }

    public function toApiArray(): array
    {
        $tz = config('app.timezone');

        $data = [
            'id'             => $this->id,
            'company_id'     => $this->company_id,
            'user_id'        => $this->user_id,
            'type'           => $this->type,
            'type_label'     => $this->type_label,
            'start_date'     => $this->start_date?->format('Y-m-d'),
            'end_date'       => $this->end_date?->format('Y-m-d'),
            'total_days'     => $this->total_days,
            'reason'         => $this->reason,
            'attachment'     => $this->attachment,
            'attachment_url' => $this->attachment_url,
            'status'         => $this->status,
            'status_label'   => self::STATUS_LABELS[$this->status] ?? $this->status,
            'approved_by'    => $this->relationLoaded('approver') && $this->approver
                ? ['id' => $this->approver->id, 'name' => $this->approver->name]
                : null,
            'approved_at'    => $this->approved_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'approval_note'  => $this->approval_note,
            'can_cancel'     => $this->status === 'pending',
            'created_at'     => $this->created_at
                ? Carbon::parse($this->created_at)->timezone($tz)->format('Y-m-d H:i:s')
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
