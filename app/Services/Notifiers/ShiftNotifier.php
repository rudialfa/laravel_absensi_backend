<?php

namespace App\Services\Notifiers;

use App\Models\Shift;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi perubahan shift oleh HR.
 *
 *   overrideCreated()   → 1 karyawan: shift khusus untuk dia
 *   overrideCanceled()  → 1 karyawan: shift khusus dibatalkan, kembali ke shift biasa
 *   groupAssigned()     → semua anggota grup: grup dapat shift baru / diubah
 */
class ShiftNotifier
{
    private const BULAN = [
        1 => 'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agu',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];

    public function __construct(private NotificationService $notif) {}

    private function tgl($date): string
    {
        $d = Carbon::parse($date);

        return $d->day . ' ' . self::BULAN[$d->month] . ' ' . $d->year;
    }

    private function period($start, $end): string
    {
        if (!$end) {
            return 'mulai ' . $this->tgl($start);
        }

        if (Carbon::parse($start)->isSameDay(Carbon::parse($end))) {
            return 'tanggal ' . $this->tgl($start);
        }

        return $this->tgl($start) . ' – ' . $this->tgl($end);
    }

    private function shiftLabel(?Shift $shift): string
    {
        if (!$shift) return 'shift baru';

        return $shift->name . ' (' . substr($shift->start_time, 0, 5) . '–' . substr($shift->end_time, 0, 5) . ')';
    }

    private function safe(callable $fn, string $event): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('Notif shift gagal', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------

    public function overrideCreated($override): void
    {
        $this->safe(function () use ($override) {
            $shift = Shift::find($override->shift_id);

            $this->notif->sendLater(
                $override->user_id,
                'shift.override_created',
                '🔄 Shift Anda diubah',
                'Shift ' . $this->shiftLabel($shift) . ' ' . $this->period($override->start_date, $override->end_date)
                    . ($override->reason ? '. Alasan: ' . $override->reason : ''),
                [
                    'category'  => 'schedule',
                    'action'    => 'shift_schedule',
                    'reference' => $override,
                    'data'      => [
                        'shift_id'   => $override->shift_id,
                        'start_date' => Carbon::parse($override->start_date)->toDateString(),
                    ],
                ]
            );
        }, 'override_created');
    }

    public function overrideCanceled($override): void
    {
        $this->safe(function () use ($override) {
            $shift = Shift::find($override->shift_id);

            $this->notif->sendLater(
                $override->user_id,
                'shift.override_canceled',
                '↩️ Perubahan shift dibatalkan',
                'Shift ' . $this->shiftLabel($shift) . ' ' . $this->period($override->start_date, $override->end_date)
                    . ' dibatalkan. Anda kembali ke shift biasa.',
                [
                    'category'  => 'schedule',
                    'action'    => 'shift_schedule',
                    'reference' => $override,
                    'data'      => [
                        'start_date' => Carbon::parse($override->start_date)->toDateString(),
                    ],
                ]
            );
        }, 'override_canceled');
    }

    /** Anggota grup yang aktif di periode assignment */
    private function groupMemberIds(int $groupId, $start, $end): array
    {
        return DB::table('shift_group_users')
            ->where('shift_group_id', $groupId)
            // keanggotaan sudah dimulai sebelum assignment berakhir
            ->when($end, fn($q) => $q->where(function ($q2) use ($end) {
                $q2->whereNull('start_date')->orWhere('start_date', '<=', $end);
            }))
            // dan belum berakhir saat assignment dimulai
            ->where(function ($q) use ($start) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $start);
            })
            ->pluck('user_id')
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    public function groupAssigned($assignment, bool $isUpdate = false): void
    {
        $this->safe(function () use ($assignment, $isUpdate) {
            $userIds = $this->groupMemberIds(
                (int) $assignment->shift_group_id,
                Carbon::parse($assignment->start_date)->toDateString(),
                $assignment->end_date ? Carbon::parse($assignment->end_date)->toDateString() : null
            );

            if (!$userIds) return;

            $shift = Shift::find($assignment->shift_id);
            $group = DB::table('shift_groups')->where('id', $assignment->shift_group_id)->value('name');

            $this->notif->sendLater(
                $userIds,
                $isUpdate ? 'shift.group_updated' : 'shift.group_assigned',
                $isUpdate ? '🔄 Jadwal shift grup diubah' : '📋 Jadwal shift baru',
                'Grup ' . ($group ?: '-') . ': ' . $this->shiftLabel($shift) . ' '
                    . $this->period($assignment->start_date, $assignment->end_date)
                    . ($assignment->note ? '. ' . $assignment->note : ''),
                [
                    'category'  => 'schedule',
                    'action'    => 'shift_schedule',
                    'reference' => $assignment,
                    'data'      => [
                        'shift_id'       => $assignment->shift_id,
                        'shift_group_id' => $assignment->shift_group_id,
                        'start_date'     => Carbon::parse($assignment->start_date)->toDateString(),
                    ],
                ]
            );
        }, 'group_assigned');
    }
}
