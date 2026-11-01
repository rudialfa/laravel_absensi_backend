<?php

namespace App\Console\Commands;

use App\Models\CompanyHoliday;
use App\Models\Leaves;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use App\Models\Schedule;
use App\Models\SubscriptionInvoice;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Kirim notifikasi CONTOH untuk setiap jenis `action`, ke 1 user,
 * memakai data asli terakhir di perusahaannya (izin, cuti, jadwal, dll).
 *
 * Gunanya: tes pop-up, isi lonceng, dan apakah klik notifikasi membuka halaman yang benar.
 *
 *   php artisan notif:demo 12                          → semua jenis sesuai role user
 *   php artisan notif:demo 12 --only=holiday_detail    → satu jenis saja
 *   php artisan notif:demo 12 --list                   → lihat daftar jenis tanpa mengirim
 *
 * Notifikasi diberi awalan [TES] dan ikut tersimpan di lonceng aplikasi.
 */
class NotifDemo extends Command
{
    protected $signature = 'notif:demo
        {user_id : ID user tujuan}
        {--only= : Kirim satu action saja, contoh: leave_detail}
        {--list : Tampilkan daftar action tanpa mengirim}
        {--delay=3 : Jeda (detik) antar notifikasi}';

    protected $description = 'Kirim notifikasi contoh untuk semua jenis action ke satu user';

    public function handle(NotificationService $notif): int
    {
        $user = User::find($this->argument('user_id'));

        if (! $user) {
            $this->error('User tidak ditemukan.');
            return self::FAILURE;
        }

        if (! $user->fcm_token) {
            $this->warn("{$user->name} belum punya fcm_token → hanya masuk lonceng, tidak ada pop-up.");
        }

        $items = $this->items($user);
        $only  = $this->option('only');

        if ($only) {
            $items = array_values(array_filter($items, fn($i) => $i['action'] === $only));
            if (! $items) {
                $this->error("Action '{$only}' tidak tersedia untuk role {$user->role}. Pakai --list untuk melihat daftar.");
                return self::FAILURE;
            }
        }

        $this->info("User: {$user->name} (role: {$user->role}, company: {$user->company_id})");
        $this->newLine();

        if ($this->option('list')) {
            $this->table(['action', 'judul', 'status'], array_map(fn($i) => [
                $i['action'],
                $i['title'],
                $i['skip'] ?? 'siap',
            ], $items));
            return self::SUCCESS;
        }

        $delay = max(0, (int) $this->option('delay'));
        $sent  = 0;

        foreach ($items as $n => $i) {
            if (! empty($i['skip'])) {
                $this->line("  ⏭  {$i['action']} — dilewati: {$i['skip']}");
                continue;
            }

            $r = $notif->send($user, 'demo.' . $i['action'], '[TES] ' . $i['title'], $i['body'], [
                'category' => $i['category'],
                'action'   => $i['action'],
                'data'     => $i['data'],
            ]);

            $status = $r['sent'] ? 'terkirim' : ($r['no_token'] ? 'tanpa token' : 'gagal push');
            $this->line("  ✔  {$i['action']} — {$status}");
            $sent++;

            if ($delay && $n < count($items) - 1) {
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info("Selesai: {$sent} notifikasi. Klik satu per satu di HP / di lonceng aplikasi.");

        return self::SUCCESS;
    }

    /** Daftar notifikasi contoh sesuai role */
    private function items(User $user): array
    {
        $cid = (int) $user->company_id;

        $latest = function (string $model, bool $own = false) use ($cid, $user) {
            try {
                $q = $model::query()->where('company_id', $cid);
                if ($own) $q->where('user_id', $user->id);
                return $q->latest('id')->first();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $isHr = $user->role === 'hr';
        $own  = ! $isHr;

        $perm     = $latest(Permission::class, $own);
        $leave    = $latest(Leaves::class, $own);
        $overtime = $latest(OvertimeRequest::class, $own);
        $schedule = $latest(Schedule::class);
        $holiday  = $latest(CompanyHoliday::class);
        $ticket   = SupportTicket::where('user_id', $user->id)->latest('id')->first();

        $none = fn($what) => "belum ada data {$what}" . ($own ? ' milik user ini' : ' di perusahaan ini');

        $list = [];

        // ---------- Pengajuan ----------
        foreach (
            [
                ['permission', $perm, 'izin'],
                ['leave', $leave, 'cuti'],
                ['overtime', $overtime, 'lembur'],
            ] as [$kind, $row, $label]
        ) {
            $list[] = [
                'action'   => $isHr ? "hr_{$kind}_detail" : "{$kind}_detail",
                'category' => 'approval',
                'title'    => $isHr ? "📝 Pengajuan {$label} baru" : "✅ Pengajuan {$label} disetujui",
                'body'     => $isHr ? "Contoh: ada pengajuan {$label} menunggu persetujuan." : "Contoh: {$label} Anda disetujui HR.",
                'data'     => ['kind' => $kind, "{$kind}_id" => (string) ($row->id ?? ''), 'employee_id' => (string) ($row->user_id ?? '')],
                'skip'     => $row ? null : $none($label),
            ];
        }

        if ($isHr) {
            $list[] = [
                'action' => 'hr_approvals',
                'category' => 'reminder',
                'title'  => '📋 Pengajuan menunggu keputusan',
                'body'   => 'Contoh ringkasan pagi: ada izin, cuti, dan lembur yang belum diputuskan.',
                'data'   => ['izin' => '1', 'cuti' => '1', 'lembur' => '1', 'laporan_bulanan' => '0'],
            ];
        } else {
            $today = now('Asia/Jakarta')->format('Y-m-d');
            $list[] = [
                'action' => 'attendance_checkin',
                'category' => 'attendance',
                'title'  => '⏰ Jangan lupa absen masuk',
                'body'   => 'Contoh pengingat absen masuk.',
                'data'   => ['date' => $today],
            ];
            $list[] = [
                'action' => 'attendance_checkout',
                'category' => 'attendance',
                'title'  => '🏠 Waktunya absen pulang',
                'body'   => 'Contoh pengingat absen pulang.',
                'data'   => ['date' => $today],
            ];
        }

        // ---------- Shift / jadwal / libur ----------
        $list[] = [
            'action' => 'shift_schedule',
            'category' => 'schedule',
            'title'  => '🔄 Shift Anda berubah',
            'body'   => 'Contoh: shift besok diubah HR.',
            'data'   => ['date' => now('Asia/Jakarta')->addDay()->format('Y-m-d')],
        ];
        $list[] = [
            'action' => 'schedule_detail',
            'category' => 'schedule',
            'title'  => '📅 Jadwal: ' . ($schedule->title ?? '-'),
            'body'   => 'Contoh notifikasi jadwal / tugas.',
            'data'   => ['schedule_id' => (string) ($schedule->id ?? ''), 'needs_response' => '0'],
            'skip'   => $schedule ? null : $none('jadwal'),
        ];
        $list[] = [
            'action' => 'holiday_detail',
            'category' => 'holiday',
            'title'  => '🏖️ Libur: ' . ($holiday->name ?? '-'),
            'body'   => 'Contoh notifikasi hari libur.',
            'data'   => [
                'holiday_id' => (string) ($holiday->id ?? ''),
                'event'      => 'created',
                'start_date' => $holiday?->start_date ? \Illuminate\Support\Carbon::parse($holiday->start_date)->format('Y-m-d') : '',
                'end_date'   => $holiday?->end_date ? \Illuminate\Support\Carbon::parse($holiday->end_date)->format('Y-m-d') : '',
            ],
            'skip'   => $holiday ? null : $none('libur'),
        ];

        // ---------- Langganan (hanya penanggung jawab billing) ----------
        if (method_exists($user, 'isBillingManager') && $user->isBillingManager()) {
            $invoice = null;
            try {
                $invoice = SubscriptionInvoice::where('company_id', $cid)->latest('id')->first();
            } catch (\Throwable $e) {
            }

            $list[] = [
                'action' => 'subscription_status',
                'category' => 'subscription',
                'title'  => '⏳ Langganan hampir habis',
                'body'   => 'Contoh: masa aktif tinggal 3 hari.',
                'data'   => ['days_left' => '3'],
            ];
            $list[] = [
                'action' => 'subscription_plans',
                'category' => 'subscription',
                'title'  => '🔒 Langganan berakhir',
                'body'   => 'Contoh: pilih paket untuk memperpanjang.',
                'data'   => [],
            ];
            $list[] = [
                'action' => 'subscription_invoice',
                'category' => 'subscription',
                'title'  => '🧾 Tagihan belum dibayar',
                'body'   => 'Contoh notifikasi invoice.',
                'data'   => [
                    'invoice_id'     => (string) ($invoice->id ?? ''),
                    'invoice_number' => (string) ($invoice->invoice_number ?? ''),
                ],
                'skip'   => $invoice ? null : 'belum ada invoice di perusahaan ini',
            ];
        }

        // ---------- Tiket bantuan ----------
        $list[] = [
            'action' => 'support_ticket_detail',
            'category' => 'system',
            'title'  => '💬 Balasan tiket bantuan',
            'body'   => 'Tim Dukungan: contoh balasan tiket.',
            'data'   => ['ticket_id' => (string) ($ticket->id ?? ''), 'status' => (string) ($ticket->status ?? '')],
            'skip'   => $ticket ? null : 'user ini belum pernah membuat tiket (buat 1 tiket dulu dari aplikasi)',
        ];

        return $list;
    }
}
