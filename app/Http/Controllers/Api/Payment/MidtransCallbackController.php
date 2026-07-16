<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\VaPaymentLog;
use App\Services\Midtrans\CallbackService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class MidtransCallbackController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
    ) {}

    // ============================================================
    // POST /api/v1/midtrans/callback
    // Didaftarkan sebagai "Payment Notification URL" di dashboard Midtrans.
    // TIDAK pakai middleware auth:sanctum.
    // ============================================================
    public function handle(): JsonResponse
    {
        $callback     = new CallbackService();
        $invoice      = $callback->getInvoice();
        $notification = $callback->getNotification();

        // Notification pakai magic __get (properti privat), jadi (array) cast
        // tidak reliable — konversi lewat json_encode/json_decode.
        $notificationArray = json_decode(json_encode($notification), true) ?? [];

        if (! $invoice) {
            Log::warning('Midtrans callback: invoice tidak ditemukan', [
                'order_id' => $notificationArray['order_id'] ?? null,
            ]);
            return response()->json(['message' => 'Invoice tidak ditemukan'], 404);
        }

        $vaPayment = $invoice->vaPayment;

        if (! $callback->isSignatureKeyVerified()) {
            VaPaymentLog::record(
                $vaPayment,
                $vaPayment?->bank ?? 'bca',
                $notificationArray,
                null,
                403,
                false,
                'Signature key tidak valid',
            );

            Log::warning('Midtrans callback: signature key tidak valid', [
                'invoice' => $invoice->invoice_number,
            ]);
            return response()->json(['message' => 'Signature key tidak valid'], 403);
        }

        if ($callback->isSuccess()) {
            if (! $invoice->isPaid()) {
                $vaPayment?->markAsPaid(
                    $vaPayment->payment_request_id,
                    $notificationArray['transaction_id'] ?? null,
                );
                $invoice->markAsPaid();

                $this->subscriptionService->activateFromInvoice($invoice);
            }
        } elseif ($callback->isExpire()) {
            $invoice->markAsExpired();
            $vaPayment?->markAsExpired();
        } elseif ($callback->isCancelled()) {
            $invoice->update(['status' => 'cancelled']);
            $vaPayment?->markAsCancelled();
        }

        VaPaymentLog::record(
            $vaPayment,
            $vaPayment?->bank ?? 'bca',
            $notificationArray,
            ['status' => 'OK'],
            200,
            true,
        );

        return response()->json(['message' => 'OK']);
    }
}
