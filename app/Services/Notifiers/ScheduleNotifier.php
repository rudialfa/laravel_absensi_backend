<?php

namespace App\Services\Notifiers;

use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi jadwal / tugas.
 *
 *   created()          → pemilik jadwal ("Jadwal baru") + peserta ("Undangan")
 *   invited()          → peserta yang baru ditambahkan
 *   updated()          → pemilik + peserta yang tidak menolak
 *   canceled()         → pemilik + peserta (jadwal dibatalkan / dihapus)
 *   responded()        → pembuat jadwal (HR): peserta menerima / menolak
 *   reminder()         → dipakai scheduler (B2c): X menit sebelum mulai
 */
class ScheduleNotifier
{
    public function __construct(private NotificationService $notif) {}

    private function place(Schedule $s): ?string
    {
        $loc = $s->location;

        if (is_array($loc)) {
            return $loc['name'] ?? $loc['address'] ?? $loc['label'] ?? null;
        }

        return is_string($loc) && $loc !== '' ? $loc : null;
    }

    private function when(Schedule $s): string
    {
        $start = $s->start_datetime;

        if (!$start) {
            return 'Waktu belum ditentukan';
        }

        $text = Schedule::DAY_NAMES[$start->dayOfWeek] . ', ' . $start->day . ' '
            . Schedule::MONTH_NAMES[$start->month] . ' ' . $start->year
            . ', ' . $start->format('H:i');

        if ($s->end_datetime) {
            $text .= '–' . $s->end_datetime->format('H:i');
        }

        $text .= ' WIB';

        if ($place = $this->place($s)) {
            $text .= ' · ' . $place;
        }

        return $text;
    }

    private function options(Schedule $s, array $extra = []): array
    {
        return [
            'category'  => 'schedule',
            'action'    => 'schedule_detail',
            'reference' => $s,
            'data'      => array_merge(['schedule_id' => $s->id], $extra),
        ];
    }

    /** Pemilik + peserta yang tidak menolak, kecuali user yang melakukan aksi */
    private function audience(Schedule $s, bool $includeDeclined = false): array
    {
        $ids = ScheduleParticipant::where('schedule_id', $s->id)
            ->when(!$includeDeclined, fn($q) => $q->where('status', '!=', 'declined'))
            ->pluck('user_id')
            ->push($s->user_id)
            ->map(fn($v) => (int) $v)
            ->unique()
            ->reject(fn($id) => $id === (int) auth()->id())
            ->values()
            ->all();

        return $ids;
    }

    private function safe(callable $fn, string $event): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('Notif jadwal gagal', ['event' => $event, 'error' => $e->getMessage()]);
        }
    }

    // ----------------------------------------------------------

    public function created(Schedule $s, array $participantIds = []): void
    {
        $this->safe(function () use ($s, $participantIds) {
            $type = \App\Models\Schedule::TYPE_LABELS[$s->type] ?? 'Jadwal';

            // Pemilik jadwal
            if ((int) $s->user_id !== (int) auth()->id()) {
                $this->notif->sendLater(
                    $s->user_id,
                    'schedule.assigned',
                    '📅 ' . $type . ' baru: ' . $s->title,
                    $this->when($s),
                    $this->options($s)
                );
            }

            // Peserta
            $this->invited($s, $participantIds);
        }, 'created');
    }

    public function invited(Schedule $s, array $userIds): void
    {
        $this->safe(function () use ($s, $userIds) {
            $ids = collect($userIds)
                ->map(fn($v) => (int) $v)
                ->reject(fn($id) => $id === (int) $s->user_id || $id === (int) auth()->id())
                ->unique()
                ->values();

            if ($ids->isEmpty()) return;

            $this->notif->sendLater(
                $ids->all(),
                'schedule.invited',
                '✉️ Undangan: ' . $s->title,
                $this->when($s) . '. Ketuk untuk menerima / menolak.',
                $this->options($s, ['needs_response' => 1])
            );
        }, 'invited');
    }

    public function updated(Schedule $s): void
    {
        $this->safe(function () use ($s) {
            $this->notif->sendLater(
                $this->audience($s),
                'schedule.updated',
                '✏️ Jadwal diubah: ' . $s->title,
                'Sekarang: ' . $this->when($s),
                $this->options($s)
            );
        }, 'updated');
    }

    public function canceled(Schedule $s): void
    {
        $this->safe(function () use ($s) {
            $this->notif->sendLater(
                $this->audience($s, true),
                'schedule.canceled',
                '❌ Jadwal dibatalkan: ' . $s->title,
                'Jadwal ' . $this->when($s) . ' dibatalkan.',
                $this->options($s)
            );
        }, 'canceled');
    }

    public function responded(Schedule $s, User $participant, string $status, ?string $note = null): void
    {
        $this->safe(function () use ($s, $participant, $status, $note) {
            $creatorId = (int) ($s->created_by ?: 0);

            if (!$creatorId || $creatorId === (int) $participant->id) return;

            $accepted = $status === 'accepted';

            $this->notif->sendLater(
                $creatorId,
                $accepted ? 'schedule.accepted' : 'schedule.declined',
                ($accepted ? '👍 ' : '👎 ') . $participant->name . ($accepted ? ' menerima' : ' menolak') . ' undangan',
                $s->title . ($note ? ' — ' . $note : ''),
                $this->options($s, ['participant_id' => $participant->id])
            );
        }, 'responded');
    }

    /** Dipanggil scheduler (B2c). $minutes = berapa menit sebelum mulai. */
    public function reminder(Schedule $s, array $userIds, int $minutes): array
    {
        $label = $minutes >= 60 && $minutes % 60 === 0
            ? ($minutes / 60) . ' jam'
            : $minutes . ' menit';

        return $this->notif->send(
            $userIds,
            'schedule.reminder',
            '⏰ ' . $label . ' lagi: ' . $s->title,
            $this->when($s),
            $this->options($s, ['offset' => $minutes])
        );
    }
}
