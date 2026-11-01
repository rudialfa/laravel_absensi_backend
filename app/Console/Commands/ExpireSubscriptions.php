<?php

namespace App\Console\Commands;

use App\Models\VaPayment;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Rapikan status langganan, invoice, dan VA yang sudah lewat waktu.
 *
 *   php artisan subscriptions:expire
 *
 * Dijalankan scheduler setiap jam (lihat app/Console/Kernel.php).
 */
class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Expire subscription, invoice, dan VA yang sudah melewati waktu aktif';

    public function handle(
        SubscriptionService $subscriptionService,
        InvoiceService      $invoiceService,
    ): int {
        $this->info('[' . now()->format('Y-m-d H:i') . '] Memulai proses expire...');

        // 1. Langganan: trial/active → grace → expired
        $subs = $subscriptionService->expireAll();
        $this->info("- Langganan berubah status : {$subs}");

        // 2. Invoice pending yang sudah lewat due_at → expired (VA-nya ikut expired)
        $invoices = $invoiceService->expireOverdueInvoices();
        $this->info("- Invoice expired          : {$invoices}");

        // 3. VA pending yang sudah lewat expired_at (yang belum ikut tertutup di langkah 2)
        $vas = 0;
        VaPayment::expired()->get()->each(function (VaPayment $va) use (&$vas) {
            $va->markAsExpired();
            $vas++;
        });
        $this->info("- VA expired               : {$vas}");

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
