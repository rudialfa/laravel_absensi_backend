<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Services\VaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BcaWebhookController extends Controller
{
    public function __construct(
        private VaPaymentService $vaPaymentService,
    ) {}

    // ============================================================
    // POST /api/webhook/bca/inquiry
    // BCA hit endpoint ini saat customer input nomor VA di ATM/mBanking
    // BCA tanya: "Ada tagihan untuk VA ini?"
    // Kita jawab dengan detail tagihan
    // ============================================================

    public function inquiry(Request $request): JsonResponse
    {
        Log::info('[BCA Webhook] Inquiry hit', [
            'ip'      => $request->ip(),
            'payload' => $request->all(),
        ]);

        // Validasi signature dari BCA (production wajib)
        // Untuk sandbox bisa di-skip dulu, aktifkan saat production
        // $this->validateBcaSignature($request);

        $response = $this->vaPaymentService->handleBcaInquiry(
            $request->all()
        );

        return response()->json($response);
    }

    // ============================================================
    // POST /api/webhook/bca/payment
    // BCA hit endpoint ini setelah customer selesai bayar VA
    // BCA kasih tahu: "Customer sudah bayar, ini detailnya"
    // Kita konfirmasi lalu aktifkan subscription
    // ============================================================

    public function payment(Request $request): JsonResponse
    {
        Log::info('[BCA Webhook] Payment Flag hit', [
            'ip'      => $request->ip(),
            'payload' => $request->all(),
        ]);

        // Validasi signature dari BCA (production wajib)
        // $this->validateBcaSignature($request);

        $response = $this->vaPaymentService->handleBcaPaymentFlag(
            $request->all()
        );

        return response()->json($response);
    }

    // ============================================================
    // VALIDASI SIGNATURE BCA (aktifkan saat production)
    // ============================================================

    /**
     * Validasi bahwa request benar-benar datang dari BCA.
     * BCA mengirim X-SIGNATURE di header menggunakan Symmetric Signature.
     *
     * Uncomment dan panggil method ini di inquiry() dan payment()
     * saat sudah production.
     */
    // private function validateBcaSignature(Request $request): void
    // {
    //     $timestamp    = $request->header('X-TIMESTAMP');
    //     $clientKey    = $request->header('X-CLIENT-KEY');
    //     $signature    = $request->header('X-SIGNATURE');
    //     $body         = $request->getContent();
    //     $method       = $request->method();
    //     $relativeUrl  = $request->getPathInfo();
    //     $accessToken  = str_replace('Bearer ', '', $request->header('Authorization', ''));
    //     $clientSecret = config('payment.bca.client_secret');
    //
    //     $hashedBody   = strtolower(hash('sha256', $body));
    //     $stringToSign = implode(':', [strtoupper($method), $relativeUrl, $accessToken, $hashedBody, $timestamp]);
    //     $expected     = base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));
    //
    //     if (! hash_equals($expected, $signature)) {
    //         Log::warning('[BCA Webhook] Signature mismatch', compact('signature', 'expected'));
    //         abort(401, 'Invalid Signature');
    //     }
    // }
}
