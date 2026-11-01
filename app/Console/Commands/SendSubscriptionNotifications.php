<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Services\NotificationService;
use App\Services\Notifiers\SubscriptionNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi LANGGANAN terjadwal — dijalankan scheduler setiap 15 menit.
 *
 *   ⏳ Trial hampir habis          H-3, H-1, hari H          (jam 09:00)
 *   📅 Langganan hampir habis      H-7, H-3, H-1, hari H     (jam 09:00)
 *   ⚠️ Masa toleransi (grace)      setiap hari selama 3 hari (jam 09:00)
 *   🔒 Langganan kedaluwarsa       sekali, saat masa toleransi habis
 *   💳 Invoice belum dibayar       sekali, 6 jam sebelum batas bayar
 *   ❌ Invoice kedaluwarsa          sekali, setelah batas bayar lewat
 *
 * ("✅ Pembayaran berhasil" dikirim langsung saat pembayaran masuk,
 *  lewat SubscriptionService::activateFromInvoice.)
 *
 * Penerima: penanggung jawab billing company (User::isBillingManager()).
 * Aman dijalankan berkali-kali: setiap notifikasi punya "key" unik supaya tidak dobel.
 *
 * TES:
 *   php artisan subscriptions:notify --at="2026-11-05 09:00" --dry-run
 */
class SendSubscriptionNotifications extends Command
{
    protected $signature = 'subscriptions:notify
                            {--at= : Simulasi waktu sekarang, contoh "2026-11-05 09:00"}
                            {--dry-run : Tampilkan saja, jangan kirim}';

    protected $description = 'Kirim notifikasi langganan (trial, masa aktif, masa toleransi, invoice)';

    /** Jam kirim pengingat harian */
    private const SEND_AT = '09:00';

    /** Pengingat sebelum berakhir (dalam hari) */
    private const TRIAL_DAYS  = [3, 1, 0];
    private const ACTIVE_DAYS = [7, 3, 1, 0];

    /** Pengingat invoice: X jam sebelum due_at */
    private const INVOICE_HOURS_BEFORE = 6;

    private bool $dry = false;
    private int $total = 0;

    private NotificationService $notif;
    private SubscriptionNotifier $subs;

    public function handle(NotificationService $notif, SubscriptionNotifier $subs): int
    {
        $this->notif = $notif;
        $this->subs  = $subs;
        $this->dry   = (bool) $this->option('dry-run');

        if ($this->option('at')) {
            Carbon::setTestNow(Carbon::parse($this->option('at'), config('app.timezone')));
            $this->warn('Simulasi waktu: ' . now()->format('Y-m-d H:i'));
        }

        try {
            $this->subscriptionReminders();
        } catch (\Throwable $e) {
            Log::error('subscriptions:notify langganan gagal', ['error' => $e->getMessage()]);
            $this->error('[langganan] ' . $e->getMessage());
        }

        try {
            $this->invoiceReminders();
        } catch (\Throwable $e) {
            Log::error('subscriptions:notify invoice gagal', ['error' => $e->getMessage()]);
            $this->error('[invoice] ' . $e->getMessage());
        }

        $this->info(($this->dry ? '[DRY RUN] ' : '') . "Selesai. Total notifikasi: {$this->total}");

        Carbon::setTestNow();

        return self::SUCCESS;
    }

    // ==========================================================
    // HELPER
    // ==========================================================

    private function alreadySent(int $userId, string $type, string $key): bool
    {
        return AppNotification::where('user_id', $userId)
            ->where('type', $type)
            ->where('data->key', $key)
            ->exists();
    }

    /** Hitung selisih hari kalender (abaikan jam) dari hari ini ke $date */
    private function daysUntil(Carbon $date): int
    {
        $today  = now()->startOfDay();
        $target = $date->copy()->timezone(config('app.timezone'))->startOfDay();

        return (int) $today->diffInDays($target, false);
    }

    private function deliver(int $companyId, string $type, string $key, string $title, string $body, array $options, string $logLabel): void
    {
        $users = $this->subs->billingManagersOf($companyId)
            ->reject(fn($u) => $this->alreadySent($u->id, $type, $key))
            ->pluck('id')
            ->all();

        if (!$users) return;

        $this->total += count($users);
        $this->line(($this->dry ? '[DRY] ' : '') . "[{$companyId}] {$logLabel} → " . count($users) . " penerima: {$title}");

        if (!$this->dry) {
            $options['category']    = 'subscription';
            $options['data']        = array_merge($options['data'] ?? [], ['key' => $key]);
            $this->notif->send($users, $type, $title, $body, $options);
        }
    }

    // ==========================================================
    // ⏳ TRIAL / 📅 LANGGANAN / ⚠️ GRACE / 🔒 EXPIRED
    // ==========================================================

    private function subscriptionReminders(): void
    {
        // Pengingat harian hanya dikirim mulai jam SEND_AT
        if (now()->lt(Carbon::parse(now()->toDateString() . ' ' . self::SEND_AT))) {
            return;
        }

        $subs = CompanySubscription::with('plan')
            ->whereIn('status', ['trial', 'active', 'grace', 'expired'])
            ->get();

        foreach ($subs as $sub) {
            // Lewati baris "placeholder": dibuat saat invoice pertama, belum pernah aktif.
            if ($sub->status === 'expired' && !$sub->has_used_trial && !$sub->last_invoice_id) {
                continue;
            }

            if (!$sub->expires_at) continue;

            $isTrial    = $sub->status === 'trial' || ($sub->plan?->is_free ?? false);
            $expiresAt  = $sub->expires_at;
            $graceUntil = $expiresAt->copy()->addDays(CompanySubscription::GRACE_PERIOD_DAYS);
            $expDate    = $expiresAt->toDateString();
            $planName   = $sub->plan->name ?? 'Langganan';

            // ------------------------------------------------------
            // 1) MASIH AKTIF → pengingat H-x
            // ------------------------------------------------------
            if (now()->lt($expiresAt)) {
                $daysLeft = $this->daysUntil($expiresAt);
                $steps    = $isTrial ? self::TRIAL_DAYS : self::ACTIVE_DAYS;

                if (!in_array($daysLeft, $steps, true)) continue;

                $when = $daysLeft === 0 ? 'hari ini' : "{$daysLeft} hari lagi";

                if ($isTrial) {
                    $type  = 'subscription.trial_ending';
                    $title = "⏳ Masa trial berakhir {$when}";
                    $body  = 'Trial berakhir ' . SubscriptionNotifier::tgl($expiresAt, true)
                        . '. Pilih paket berlangganan supaya absensi tetap berjalan.';
                } else {
                    $type  = 'subscription.expiring';
                    $title = "📅 Langganan berakhir {$when}";
                    $body  = "Paket {$planName} berakhir " . SubscriptionNotifier::tgl($expiresAt, true)
                        . '. Perpanjang sekarang supaya tidak terputus.';
                }

                $this->deliver(
                    $sub->company_id,
                    $type,
                    "ending:{$expDate}:{$daysLeft}",
                    $title,
                    $body,
                    ['action' => 'subscription_status', 'data' => ['expires_at' => $expDate, 'days_left' => $daysLeft]],
                    $isTrial ? "trial_ending H-{$daysLeft}" : "expiring H-{$daysLeft}"
                );

                continue;
            }

            // ------------------------------------------------------
            // 2) MASA TOLERANSI → setiap hari
            // ------------------------------------------------------
            if (now()->lt($graceUntil)) {
                if (!$this->dry && $sub->status !== 'grace') {
                    $sub->syncStatus();
                }

                $graceLeft = max(0, $this->daysUntil($graceUntil));
                $today     = now()->toDateString();

                $this->deliver(
                    $sub->company_id,
                    'subscription.grace',
                    "grace:{$expDate}:{$today}",
                    $graceLeft > 0
                        ? "⚠️ Masa toleransi: sisa {$graceLeft} hari"
                        : '⚠️ Masa toleransi berakhir hari ini',
                    ($isTrial ? 'Masa trial' : "Paket {$planName}") . ' sudah berakhir ' . SubscriptionNotifier::tgl($expiresAt)
                        . '. Aplikasi akan dikunci ' . SubscriptionNotifier::tgl($graceUntil, true)
                        . ' jika belum diperpanjang.',
                    ['action' => 'subscription_status', 'data' => ['expires_at' => $expDate, 'grace_until' => $graceUntil->toDateString(), 'days_left' => $graceLeft]],
                    "grace sisa {$graceLeft}"
                );

                continue;
            }

            // ------------------------------------------------------
            // 3) SUDAH KEDALUWARSA → sekali saja
            //    (hanya dalam 3 hari setelah dikunci, supaya data lama tidak ikut dikirimi)
            // ------------------------------------------------------
            if (now()->lt($graceUntil->copy()->addDays(3))) {
                if (!$this->dry && $sub->status !== 'expired') {
                    $sub->syncStatus();
                }

                $this->deliver(
                    $sub->company_id,
                    'subscription.expired',
                    "expired:{$expDate}",
                    '🔒 Langganan kedaluwarsa',
                    'Fitur aplikasi sudah dikunci sejak ' . SubscriptionNotifier::tgl($graceUntil, true)
                        . '. Perpanjang langganan untuk membuka kembali.',
                    ['action' => 'subscription_plans', 'data' => ['expires_at' => $expDate]],
                    'expired'
                );
            }
        }
    }

    // ==========================================================
    // 💳 INVOICE BELUM DIBAYAR / ❌ INVOICE KEDALUWARSA
    // ==========================================================

    private function invoiceReminders(): void
    {
        // 💳 Pending, batas bayar tinggal <= 6 jam
        $dueSoon = SubscriptionInvoice::with(['plan', 'vaPayment'])
            ->where('status', 'pending')
            ->whereBetween('due_at', [now(), now()->addHours(self::INVOICE_HOURS_BEFORE)])
            ->get();

        foreach ($dueSoon as $inv) {
            $va = $inv->vaPayment;

            $body = $inv->invoice_number . ' sebesar ' . SubscriptionNotifier::rupiah($inv->total_amount)
                . ($va ? ' via VA ' . strtoupper($va->bank) . ' ' . $va->va_number : '')
                . '. Batas bayar ' . SubscriptionNotifier::tgl($inv->due_at, true) . '.';

            $this->deliver(
                $inv->company_id,
                'subscription.invoice_unpaid',
                "invoice_due:{$inv->id}",
                '💳 Invoice belum dibayar',
                $body,
                [
                    'action'    => 'subscription_invoice',
                    'reference' => $inv,
                    'data'      => ['invoice_id' => $inv->id, 'invoice_number' => $inv->invoice_number],
                ],
                "invoice_unpaid #{$inv->id}"
            );
        }

        // ❌ Lewat batas bayar dalam 24 jam terakhir, belum dibayar
        $expired = SubscriptionInvoice::with('plan')
            ->whereIn('status', ['pending', 'expired'])
            ->whereBetween('due_at', [now()->subDay(), now()])
            ->get();

        foreach ($expired as $inv) {
            // Kalau sudah ada invoice yang lebih baru, tidak perlu diingatkan lagi
            $hasNewer = SubscriptionInvoice::where('company_id', $inv->company_id)
                ->where('id', '>', $inv->id)
                ->exists();

            if ($hasNewer) continue;

            $this->deliver(
                $inv->company_id,
                'subscription.invoice_expired',
                "invoice_expired:{$inv->id}",
                '❌ Invoice kedaluwarsa',
                $inv->invoice_number . ' (' . ($inv->plan->name ?? '-') . ') tidak dibayar sampai batas waktu. '
                    . 'Silakan pilih paket lagi untuk membuat tagihan baru.',
                [
                    'action'    => 'subscription_plans',
                    'reference' => $inv,
                    'data'      => ['invoice_id' => $inv->id, 'invoice_number' => $inv->invoice_number],
                ],
                "invoice_expired #{$inv->id}"
            );
        }
    }
}
