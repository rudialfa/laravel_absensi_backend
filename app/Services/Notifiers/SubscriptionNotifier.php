<?php

namespace App\Services\Notifiers;

use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Notifikasi LANGGANAN (trial, invoice, pembayaran VA, masa aktif).
 *
 * Penerima: semua user aktif di company yang isBillingManager() = true
 * (HR untuk company, dan role penanggung jawab billing untuk tipe lain
 * sesuai config subscription.billing_roles).
 */
class SubscriptionNotifier
{
    private const HARI  = [0 => 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function __construct(private NotificationService $notif) {}

    // ============================================================
    // PENERIMA
    // ============================================================

    /** @return Collection<User> */
    public function billingManagersOf(int $companyId): Collection
    {
        return User::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn(User $u) => $u->isBillingManager())
            ->values();
    }

    // ============================================================
    // FORMAT
    // ============================================================

    public static function tgl(?Carbon $d, bool $withTime = false): string
    {
        if (!$d) return '-';

        $d = $d->copy()->timezone(config('app.timezone'));

        $text = self::HARI[$d->dayOfWeek] . ', ' . $d->day . ' ' . self::BULAN[$d->month] . ' ' . $d->year;

        return $withTime ? $text . ' ' . $d->format('H:i') . ' WIB' : $text;
    }

    public static function rupiah($amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    // ============================================================
    // ✅ PEMBAYARAN BERHASIL
    // Dipanggil dari SubscriptionService::activateFromInvoice()
    // (webhook Midtrans, cek VA manual, verifikasi superadmin).
    // ============================================================

    public function paymentSuccess(SubscriptionInvoice $invoice, CompanySubscription $subscription): void
    {
        $invoice->loadMissing(['plan', 'vaPayment']);

        $users = $this->billingManagersOf($invoice->company_id);

        if ($users->isEmpty()) return;

        $bank = $invoice->vaPayment ? ' via VA ' . strtoupper($invoice->vaPayment->bank) : '';

        $this->notif->sendLater(
            $users,
            'subscription.payment_success',
            '✅ Pembayaran berhasil',
            self::rupiah($invoice->total_amount) . $bank . ' untuk paket ' . ($invoice->plan->name ?? '-')
                . ' sudah diterima. Langganan aktif sampai ' . self::tgl($subscription->expires_at) . '.',
            [
                'category'  => 'subscription',
                'action'    => 'subscription_invoice',
                'reference' => $invoice,
                'data'      => [
                    'key'            => 'paid:' . $invoice->id,
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'expires_at'     => $subscription->expires_at?->toDateString(),
                ],
            ],
        );
    }
}
