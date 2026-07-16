<?php

namespace App\Services\Midtrans;

use App\Models\SubscriptionInvoice;
use Midtrans\Notification;

class CallbackService extends Midtrans
{
    protected $notification;
    protected ?SubscriptionInvoice $invoice = null;
    protected $serverKey;

    public function __construct()
    {
        parent::__construct();

        $this->serverKey = config('midtrans.server_key');
        $this->_handleNotification();
    }

    public function isSignatureKeyVerified(): bool
    {
        return $this->_createLocalSignatureKey() === $this->notification->signature_key;
    }

    public function isSuccess(): bool
    {
        $statusCode        = $this->notification->status_code;
        $transactionStatus = $this->notification->transaction_status;
        $fraudStatus       = !empty($this->notification->fraud_status)
            ? ($this->notification->fraud_status == 'accept')
            : true;

        return ($statusCode == 200 && $fraudStatus
            && in_array($transactionStatus, ['capture', 'settlement']));
    }

    public function isExpire(): bool
    {
        return $this->notification->transaction_status == 'expire';
    }

    public function isCancelled(): bool
    {
        return $this->notification->transaction_status == 'cancel'
            || $this->notification->transaction_status == 'deny';
    }

    public function getNotification()
    {
        return $this->notification;
    }

    public function getInvoice(): ?SubscriptionInvoice
    {
        return $this->invoice;
    }

    protected function _createLocalSignatureKey(): string
    {
        $orderId     = $this->invoice->invoice_number ?? $this->notification->order_id;
        $statusCode  = $this->notification->status_code;
        $grossAmount = $this->notification->gross_amount; // string persis dari Midtrans
        $serverKey   = $this->serverKey;

        $input = $orderId . $statusCode . $grossAmount . $serverKey;

        return openssl_digest($input, 'sha512');
    }

    protected function _handleNotification(): void
    {
        $notification = new Notification();

        // order_id yang dikirim Midtrans = invoice_number kita
        $invoice = SubscriptionInvoice::where('invoice_number', $notification->order_id)
            ->with(['vaPayment', 'plan', 'company'])
            ->first();

        $this->notification = $notification;
        $this->invoice       = $invoice;
    }
}
