<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use App\Services\VaPaymentService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Expire subscription, invoice, dan VA yang sudah melewati waktu aktif';

    public function handle(
        SubscriptionService $subscriptionService,
        InvoiceService      $invoiceService,
        VaPaymentService    $vaPaymentService,
    ): void {
        $this->info('[' . now() . '] Memulai proses expire...');

        // 1. Expire subscription
        $subs = $subscriptionService->expireAll();
        $this->info("✓ Subscription expired : {$subs}");

        // 2. Expire invoice overdue
        $invoices = $invoiceService->expireOverdue();
        $this->info("✓ Invoice expired      : {$invoices}");

        // 3. Expire VA overdue
        $vas = $vaPaymentService->expireAll();
        $this->info("✓ VA Payment expired   : {$vas}");

        $this->info('Selesai.');
    }
}
