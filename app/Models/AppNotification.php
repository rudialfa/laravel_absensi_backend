<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $guarded = [];

    protected $casts = [
        'data'         => 'array',
        'read_at'      => 'datetime',
        'push_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ----------------------------------------------------------
    // SCOPES
    // ----------------------------------------------------------

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // ----------------------------------------------------------
    // FORMAT API
    // ----------------------------------------------------------

    /** "Baru saja", "5 menit lalu", "Kemarin 14:30", "20 Okt 14:30" */
    public function getTimeLabelAttribute(): string
    {
        $tz  = config('app.timezone');
        $at  = Carbon::parse($this->created_at)->timezone($tz);
        $now = now()->timezone($tz);

        $diff = (int) $at->diffInMinutes($now, true);

        if ($diff < 1)  return 'Baru saja';
        if ($diff < 60) return $diff . ' menit lalu';
        if ($at->isSameDay($now)) return $at->format('H:i');
        if ($at->isSameDay($now->copy()->subDay())) return 'Kemarin ' . $at->format('H:i');

        $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        return $at->day . ' ' . $bulan[$at->month]
            . ($at->year !== $now->year ? ' ' . $at->year : '')
            . ' ' . $at->format('H:i');
    }

    public function toApiArray(): array
    {
        $tz = config('app.timezone');

        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'category'       => $this->category,
            'title'          => $this->title,
            'body'           => $this->body,
            'data'           => $this->data ?? (object) [],
            'action'         => $this->action,
            'reference_type' => $this->reference_type,
            'reference_id'   => $this->reference_id,
            'is_read'        => $this->read_at !== null,
            'read_at'        => $this->read_at?->timezone($tz)->format('Y-m-d H:i:s'),
            'time_label'     => $this->time_label,
            'created_at'     => Carbon::parse($this->created_at)->timezone($tz)->format('Y-m-d H:i:s'),
        ];
    }
}
