<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'company_types'       => 'array',
        'company_ids'         => 'array',
        'user_ids'            => 'array',
        'roles'               => 'array',
        'only_active_tenants' => 'boolean',
        'scheduled_at'        => 'datetime',
        'started_at'          => 'datetime',
        'finished_at'         => 'datetime',
        'retracted_at'        => 'datetime',
    ];

    public const STATUSES = [
        'draft'     => 'Draft',
        'scheduled' => 'Terjadwal',
        'sending'   => 'Sedang dikirim',
        'sent'      => 'Terkirim',
        'failed'    => 'Gagal',
        'canceled'  => 'Dibatalkan',
    ];

    public const STATUS_BADGE = [
        'draft'     => 'secondary',
        'scheduled' => 'info',
        'sending'   => 'warning',
        'sent'      => 'success',
        'failed'    => 'danger',
        'canceled'  => 'light',
    ];

    public const TARGETS = [
        'all'       => 'Semua pengguna tenant',
        'types'     => 'Jenis tenant tertentu',
        'companies' => 'Tenant tertentu',
        'users'     => 'Pengguna tertentu',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Notifikasi per penerima yang dibuat dari pengumuman ini */
    public function notifications()
    {
        return $this->hasMany(AppNotification::class, 'reference_id')
            ->where('reference_type', 'BroadcastNotification');
    }

    /** Masih bisa diubah (belum mulai dikirim) */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }
}
