<?php

namespace App\Services;

use App\Models\SubscriptionInvoice;
use App\Models\VaPayment;
use App\Services\Midtrans\CreateVAService;
use App\Services\Midtrans\Midtrans;
use Midtrans\Transaction;

class VaPaymentService
{
    public function createVa(SubscriptionInvoice $invoice, string $bank): VaPayment
    {
        $service = new CreateVAService($invoice, $bank);

        return $service->getVA();
    }

    public function checkStatus(VaPayment $vaPayment): array
    {
        new Midtrans(); // inisialisasi config

        $result = Transaction::status($vaPayment->invoice->invoice_number);

        $transactionStatus = $result->transaction_status;
        $fraudStatus       = $result->fraud_status ?? 'accept';

        if (in_array($transactionStatus, ['capture', 'settlement']) && $fraudStatus === 'accept') {
            $vaPayment->markAsPaid(
                $vaPayment->payment_request_id,
                $result->transaction_id ?? null,
            );
            $vaPayment->invoice->markAsPaid();

            app(SubscriptionService::class)->activateFromInvoice($vaPayment->invoice);
        } elseif ($transactionStatus === 'expire') {
            $vaPayment->markAsExpired();
            $vaPayment->invoice->markAsExpired();
        } elseif (in_array($transactionStatus, ['cancel', 'deny'])) {
            $vaPayment->markAsCancelled();
            $vaPayment->invoice->update(['status' => 'cancelled']);
        }

        return (array) $result;
    }
}
