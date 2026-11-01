<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * SATU pintu untuk semua notifikasi aplikasi.
 *
 *  1. Simpan ke tabel app_notifications (kotak masuk di aplikasi)
 *  2. Kirim push FCM ke HP penerima
 *
 * Contoh pemakaian di controller:
 *
 *   app(NotificationService::class)->send(
 *       users:    $leave->user,                 // User | id | array | Collection
 *       type:     'leave.approved',
 *       title:    'Cuti disetujui ✅',
 *       body:     'Cuti 27–29 Okt disetujui oleh HR.',
 *       options:  [
 *           'category'  => 'approval',
 *           'action'    => 'leave_detail',
 *           'reference' => $leave,              // otomatis isi reference_type & id
 *           'data'      => ['leave_id' => $leave->id],
 *       ],
 *   );
 *
 * Opsi:
 *   category  : chat|approval|reminder|attendance|schedule|holiday|report|payroll|school|subscription|system
 *   action    : nama halaman tujuan di aplikasi (lihat daftar ACTION di CATATAN-API)
 *   reference : model Eloquent yang dirujuk
 *   data      : data tambahan (akan dikirim juga di payload FCM)
 *   store     : false = tidak disimpan ke kotak masuk (contoh: chat)
 *   push      : false = hanya simpan, tanpa push
 *   later     : true  = push dikirim setelah response (tidak bikin API lambat)
 */
class NotificationService
{
    public function __construct(private FcmService $fcm) {}

    /**
     * Terima User / id / array / Collection, lalu SELALU ambil ulang dari database.
     * Alasannya: User yang dimuat lewat relasi sering hanya berisi sebagian kolom
     * (contoh 'user:id,name'), sehingga fcm_token kosong padahal sebenarnya ada.
     *
     * @return Collection<User>
     */
    private function resolveUsers($users): Collection
    {
        $items = $users instanceof Collection
            ? $users
            : collect(is_iterable($users) ? $users : [$users]);

        $ids = $items
            ->map(fn($u) => $u instanceof User ? $u->getKey() : $u)
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)
            ->where('is_active', true)
            ->get(['id', 'company_id', 'fcm_token']);
    }

    /**
     * @return array{stored:int, sent:int, failed:int, no_token:int}
     */
    public function send($users, string $type, string $title, string $body, array $options = []): array
    {
        $recipients = $this->resolveUsers($users);

        $store     = $options['store'] ?? true;
        $push      = $options['push'] ?? true;
        $category  = $options['category'] ?? 'system';
        $action    = $options['action'] ?? null;
        $data      = $options['data'] ?? [];
        $reference = $options['reference'] ?? null;

        $refType = $reference ? class_basename($reference) : ($options['reference_type'] ?? null);
        $refId   = $reference ? $reference->getKey() : ($options['reference_id'] ?? null);

        $result = ['stored' => 0, 'sent' => 0, 'failed' => 0, 'no_token' => 0];

        $jobs = [];

        foreach ($recipients as $user) {
            $notif = null;

            if ($store) {
                $notif = AppNotification::create([
                    'company_id'     => $user->company_id,
                    'user_id'        => $user->id,
                    'type'           => $type,
                    'category'       => $category,
                    'title'          => $title,
                    'body'           => $body,
                    'data'           => $data ?: null,
                    'action'         => $action,
                    'reference_type' => $refType,
                    'reference_id'   => $refId,
                    'push_status'    => $push ? null : 'skipped',
                ]);
                $result['stored']++;
            }

            if ($push) {
                $jobs[] = [$user, $notif];
            }
        }

        if (!$jobs) {
            return $result;
        }

        $runPush = function () use ($jobs, $type, $category, $action, $title, $body, $data, $refType, $refId, &$result) {
            foreach ($jobs as [$user, $notif]) {
                if (empty($user->fcm_token)) {
                    $notif?->update(['push_status' => 'no_token']);
                    $result['no_token']++;
                    continue;
                }

                $payload = array_merge($data, [
                    'type'            => $type,
                    'category'        => $category,
                    'action'          => $action ?? '',
                    'notification_id' => $notif?->id ?? '',
                    'reference_type'  => $refType ?? '',
                    'reference_id'    => $refId ?? '',
                ]);

                $r = $this->fcm->sendToToken($user->fcm_token, $title, $body, $payload);

                if ($r['ok']) {
                    $notif?->update(['push_status' => 'sent', 'push_sent_at' => now()]);
                    $result['sent']++;
                } else {
                    $notif?->update(['push_status' => 'failed']);
                    $result['failed']++;

                    if ($r['invalid_token']) {
                        User::where('fcm_token', $user->fcm_token)->update(['fcm_token' => null]);
                    }
                }
            }
        };

        if (!$this->fcm->isConfigured()) {
            Log::warning('Push dilewati: FCM belum dikonfigurasi', ['type' => $type]);
            return $result;
        }

        if ($options['later'] ?? false) {
            // Kirim setelah response selesai → API tidak menunggu proses push
            app()->terminating(function () use ($runPush, $type) {
                try {
                    $runPush();
                } catch (\Throwable $e) {
                    Log::error('Gagal kirim push', ['type' => $type, 'error' => $e->getMessage()]);
                }
            });

            return $result;
        }

        try {
            $runPush();
        } catch (\Throwable $e) {
            Log::error('Gagal kirim push', ['type' => $type, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    /** Sama dengan send(), tapi push dikirim setelah response (untuk dipanggil dari controller). */
    public function sendLater($users, string $type, string $title, string $body, array $options = []): array
    {
        return $this->send($users, $type, $title, $body, array_merge($options, ['later' => true]));
    }

    /** HR aktif di sebuah company (penerima notifikasi "menunggu persetujuan"). */
    public function hrOf(int $companyId): Collection
    {
        return User::where('company_id', $companyId)
            ->where('role', 'hr')
            ->where('is_active', true)
            ->get(['id', 'company_id', 'fcm_token']);
    }

    /** Karyawan aktif di sebuah company. */
    public function employeesOf(int $companyId): Collection
    {
        return User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->get(['id', 'company_id', 'fcm_token']);
    }
}
