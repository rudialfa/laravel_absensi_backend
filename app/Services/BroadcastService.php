<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\BroadcastNotification;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Kirim pengumuman massal (BroadcastNotification) lewat NotificationService,
 * jadi tiap penerima dapat notifikasi di kotak masuk aplikasi + push FCM.
 *
 * Dipanggil oleh:
 *  - command `broadcasts:send` (scheduler tiap menit) untuk yang terjadwal,
 *  - tombol "Kirim sekarang" di panel superadmin (diproses setelah response).
 */
class BroadcastService
{
    /** Jumlah penerima per putaran kirim */
    private const CHUNK = 300;

    public function __construct(private NotificationService $notif) {}

    /**
     * Query penerima berdasarkan target.
     * $spec: target, company_types, company_ids, user_ids, roles, only_active_tenants
     */
    public function audience(array $spec): Builder
    {
        $target = $spec['target'] ?? 'all';

        return User::query()
            ->where('users.is_active', true)
            // Hanya pengguna tenant (superadmin/staff internal tidak termasuk)
            ->whereNotNull('users.company_id')
            ->when($target === 'types', fn($q) => $q->whereIn('users.company_id', Company::whereIn('type', $spec['company_types'] ?: ['-'])->select('id')))
            ->when($target === 'companies', fn($q) => $q->whereIn('users.company_id', $spec['company_ids'] ?: [0]))
            ->when($target === 'users', fn($q) => $q->whereIn('users.id', $spec['user_ids'] ?: [0]))
            ->when(!empty($spec['roles']), fn($q) => $q->whereIn('users.role', $spec['roles']))
            ->when($spec['only_active_tenants'] ?? true, fn($q) => $q->whereIn('users.company_id', Company::where('is_active', true)->select('id')));
    }

    /** Perkiraan jumlah penerima & yang punya token HP */
    public function estimate(array $spec): array
    {
        $q = $this->audience($spec);

        return [
            'users'      => (clone $q)->count(),
            'with_token' => (clone $q)->whereNotNull('users.fcm_token')->where('users.fcm_token', '<>', '')->count(),
            'companies'  => (clone $q)->distinct()->count('users.company_id'),
        ];
    }

    public function specOf(BroadcastNotification $b): array
    {
        return [
            'target'              => $b->target,
            'company_types'       => $b->company_types ?? [],
            'company_ids'         => $b->company_ids ?? [],
            'user_ids'            => $b->user_ids ?? [],
            'roles'               => $b->roles ?? [],
            'only_active_tenants' => $b->only_active_tenants,
        ];
    }

    /** Kirim semua pengumuman terjadwal yang sudah waktunya. Return jumlah yang diproses. */
    public function processDue(): int
    {
        // Pengiriman yang macet > 30 menit (server mati di tengah jalan) ditandai gagal
        BroadcastNotification::where('status', 'sending')
            ->where('started_at', '<', now()->subMinutes(30))
            ->update(['status' => 'failed', 'error' => 'Pengiriman terhenti di tengah jalan (lebih dari 30 menit). Cek log server.', 'finished_at' => now()]);

        $count = 0;
        BroadcastNotification::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->pluck('id')
            ->each(function ($id) use (&$count) {
                if ($this->process($id)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Kirim 1 pengumuman. Aman dipanggil dobel: status diklaim secara atomik
     * (scheduled → sending), jadi hanya 1 proses yang benar-benar mengirim.
     */
    public function process(int $id): bool
    {
        $claimed = BroadcastNotification::where('id', $id)
            ->where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->update(['status' => 'sending', 'started_at' => now(), 'error' => null]);

        if (!$claimed) {
            return false;
        }

        @set_time_limit(0);

        $b = BroadcastNotification::findOrFail($id);
        $totals = ['recipients' => 0, 'stored' => 0, 'sent' => 0, 'failed' => 0, 'no_token' => 0];

        try {
            $this->audience($this->specOf($b))
                ->select('users.id')
                ->orderBy('users.id')
                ->chunkById(self::CHUNK, function ($users) use ($b, &$totals) {
                    $r = $this->notif->send($users->pluck('id')->all(), 'broadcast.announcement', $b->title, $b->body, [
                        'category'       => $b->category ?: 'system',
                        'action'         => $b->action,
                        'reference_type' => 'BroadcastNotification',
                        'reference_id'   => $b->id,
                        'data'           => array_filter(['broadcast_id' => $b->id, 'url' => $b->url]),
                    ]);

                    $totals['recipients'] += $users->count();
                    foreach (['stored', 'sent', 'failed', 'no_token'] as $k) {
                        $totals[$k] += $r[$k];
                    }

                    // Simpan progres supaya terlihat di panel selama proses berjalan
                    $b->forceFill([
                        'recipients_count' => $totals['recipients'],
                        'stored_count'     => $totals['stored'],
                        'sent_count'       => $totals['sent'],
                        'failed_count'     => $totals['failed'],
                        'no_token_count'   => $totals['no_token'],
                    ])->save();
                }, 'users.id', 'id');

            $b->forceFill(['status' => 'sent', 'finished_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Broadcast gagal', ['id' => $id, 'error' => $e->getMessage()]);
            $b->forceFill(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return true;
    }

    /** Tarik pengumuman dari kotak masuk semua penerima. Return jumlah notifikasi yang dihapus. */
    public function retract(BroadcastNotification $b): int
    {
        $deleted = AppNotification::where('reference_type', 'BroadcastNotification')
            ->where('reference_id', $b->id)
            ->delete();

        $b->forceFill(['retracted_at' => now()])->save();

        return $deleted;
    }

    /** Statistik dari app_notifications (dibaca, push) */
    public function stats(BroadcastNotification $b): object
    {
        return AppNotification::where('reference_type', 'BroadcastNotification')
            ->where('reference_id', $b->id)
            ->toBase()
            ->selectRaw("count(*) as total,
                sum(read_at is not null) as read_count,
                sum(push_status = 'sent') as push_sent,
                sum(push_status = 'failed') as push_failed,
                sum(push_status = 'no_token') as push_no_token")
            ->first();
    }
}
