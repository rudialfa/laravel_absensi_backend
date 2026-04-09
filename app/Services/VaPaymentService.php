<?php

namespace App\Services;

use App\Models\SubscriptionInvoice;
use App\Models\VaPayment;
use App\Models\VaPaymentLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VaPaymentService
{
    // ============================================================
    // KONFIGURASI BANK
    // ============================================================

    private function bcaConfig(): array
    {
        return [
            'base_url'           => config('payment.bca.base_url', 'https://sandbox.bca.co.id'),
            'client_id'          => config('payment.bca.client_id'),
            'client_secret'      => config('payment.bca.client_secret'),
            'api_key'            => config('payment.bca.api_key'),
            'api_secret'         => config('payment.bca.api_secret'),
            'partner_service_id' => config('payment.bca.partner_service_id'), // 8 digit
            'private_key'        => config('payment.bca.private_key'),
            'channel_id'         => '95231',  // WSID BCA Virtual Account
        ];
    }

    private function mandiriConfig(): array
    {
        return [
            'base_url'      => config('payment.mandiri.base_url', 'https://sandbox.mandiri.co.id'),
            'client_id'     => config('payment.mandiri.client_id'),
            'client_secret' => config('payment.mandiri.client_secret'),
            'company_code'  => config('payment.mandiri.company_code'),
        ];
    }

    // ============================================================
    // BUAT VA — entry point utama
    // ============================================================

    /**
     * Buat Virtual Account untuk invoice yang sudah dibuat.
     * Dipanggil dari Controller setelah invoice berhasil dibuat.
     *
     * @param  SubscriptionInvoice $invoice
     * @param  string              $bank     'bca' | 'mandiri'
     * @return VaPayment
     *
     * @throws \Exception
     */
    public function createVa(SubscriptionInvoice $invoice, string $bank): VaPayment
    {
        if (! $invoice->isPending()) {
            throw new \Exception('Invoice tidak dalam status pending.');
        }

        // Jika sudah ada VA aktif untuk invoice ini, return yang lama
        $existing = VaPayment::where('invoice_id', $invoice->id)
            ->where('status', 'pending')
            ->where('expired_at', '>', now())
            ->first();

        if ($existing) {
            return $existing;
        }

        return match ($bank) {
            'bca'     => $this->createBcaVa($invoice),
            'mandiri' => $this->createMandiriVa($invoice),
            default   => throw new \Exception("Bank '{$bank}' tidak didukung."),
        };
    }

    // ============================================================
    // BCA VA
    // ============================================================

    /**
     * Buat VA BCA menggunakan SNAP API.
     *
     * BCA model VA adalah "biller" — BCA yang akan callback ke endpoint
     * kita saat ada inquiry & payment. VA number tidak di-generate oleh
     * API call ini, melainkan sudah kita tentukan sendiri berdasarkan
     * partnerServiceId + customerNo.
     */
    private function createBcaVa(SubscriptionInvoice $invoice): VaPayment
    {
        $cfg        = $this->bcaConfig();
        $customerNo = $this->generateCustomerNo($invoice);
        $vaNumber   = $cfg['partner_service_id'] . $customerNo;

        // Simpan ke DB — BCA biller tidak butuh API call registrasi VA.
        // BCA akan hit endpoint inquiry kita saat customer input nomor VA
        // di ATM/mBanking. Kita cukup siapkan data untuk di-return saat inquiry.
        $vaPayment = VaPayment::create([
            'invoice_id'         => $invoice->id,
            'company_id'         => $invoice->company_id,
            'bank'               => 'bca',
            'va_number'          => $vaNumber,
            'va_name'            => substr($invoice->company->name, 0, 100),
            'amount'             => $invoice->total_amount,
            'status'             => 'pending',
            'partner_service_id' => $cfg['partner_service_id'],
            'customer_no'        => $customerNo,
            'created_va_at'      => now(),
            'expired_at'         => $invoice->due_at,
        ]);

        Log::info('[BCA VA] Created', [
            'va_number'  => $vaNumber,
            'invoice'    => $invoice->invoice_number,
            'amount'     => $invoice->total_amount,
            'expired_at' => $invoice->due_at,
        ]);

        return $vaPayment;
    }

    /**
     * Buat VA Mandiri.
     * Mandiri menggunakan API call untuk registrasi VA ke server Mandiri.
     */
    private function createMandiriVa(SubscriptionInvoice $invoice): VaPayment
    {
        $cfg         = $this->mandiriConfig();
        $customerNo  = $this->generateCustomerNo($invoice);
        $vaNumber    = $cfg['company_code'] . $customerNo;
        $accessToken = $this->getMandiriAccessToken();
        $timestamp   = now()->format('Y-m-d\TH:i:sP');
        $externalId  = $this->generateExternalId();

        $payload = [
            'partnerServiceId'   => $cfg['company_code'],
            'customerNo'         => $customerNo,
            'virtualAccountNo'   => $vaNumber,
            'virtualAccountName' => substr($invoice->company->name, 0, 100),
            'trxId'              => (string) $invoice->id,
            'totalAmount'        => [
                'value'    => number_format($invoice->total_amount, 2, '.', ''),
                'currency' => 'IDR',
            ],
            'expiredDate' => $invoice->due_at->toIso8601String(),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $accessToken,
                'Content-Type'   => 'application/json',
                'X-TIMESTAMP'    => $timestamp,
                'X-SIGNATURE'    => $this->generateSymmetricSignature(
                    method: 'POST',
                    relativeUrl: '/transfer-va/create-va',
                    accessToken: $accessToken,
                    body: $payload,
                    timestamp: $timestamp,
                    clientSecret: $cfg['client_secret']
                ),
                'X-PARTNER-ID'   => $cfg['company_code'],
                'X-EXTERNAL-ID'  => $externalId,
                'CHANNEL-ID'     => '95231',
            ])->post($cfg['base_url'] . '/transfer-va/create-va', $payload);

            $responseBody = $response->json();
        } catch (\Exception $e) {
            Log::error('[Mandiri VA] HTTP error: ' . $e->getMessage());
            throw new \Exception('Gagal menghubungi server Mandiri: ' . $e->getMessage());
        }

        $vaPayment = VaPayment::create([
            'invoice_id'         => $invoice->id,
            'company_id'         => $invoice->company_id,
            'bank'               => 'mandiri',
            'va_number'          => $vaNumber,
            'va_name'            => substr($invoice->company->name, 0, 100),
            'amount'             => $invoice->total_amount,
            'status'             => 'pending',
            'partner_service_id' => $cfg['company_code'],
            'customer_no'        => $customerNo,
            'created_va_at'      => now(),
            'expired_at'         => $invoice->due_at,
            'bank_response'      => $responseBody,
        ]);

        Log::info('[Mandiri VA] Created', [
            'va_number'  => $vaNumber,
            'invoice'    => $invoice->invoice_number,
            'amount'     => $invoice->total_amount,
            'expired_at' => $invoice->due_at,
        ]);

        return $vaPayment;
    }

    // ============================================================
    // HANDLE INQUIRY dari BCA (webhook masuk)
    // ============================================================

    /**
     * Proses inquiry yang dikirim BCA ke endpoint kita.
     * BCA menanyakan: "Ada tagihan tidak untuk VA nomor ini?"
     * Kita harus balas dengan detail tagihan atau error.
     *
     * @param  array $payload  Request body dari BCA
     * @return array           Response yang dikirim balik ke BCA
     */
    public function handleBcaInquiry(array $payload): array
    {
        $vaNumber         = trim($payload['virtualAccountNo'] ?? '');
        $inquiryRequestId = $payload['inquiryRequestId'] ?? '';

        $vaPayment = VaPayment::where('va_number', $vaNumber)
            ->where('bank', 'bca')
            ->with(['invoice.plan', 'invoice.company'])
            ->first();

        // VA tidak ditemukan
        if (! $vaPayment) {
            $response = $this->buildInquiryErrorResponse(
                responseCode: '4042412',
                responseMessage: 'Invalid Bill/Virtual Account [VA not found]',
                inquiryReason: ['english' => 'VA Not Found', 'indonesia' => 'VA Tidak Ditemukan'],
                payload: $payload,
            );
            $this->saveLog(null, 'bca', 'inquiry', $payload, $response, false, 'VA not found');
            return $response;
        }

        // Simpan inquiryRequestId dari BCA untuk dipakai saat payment flag
        $vaPayment->update(['inquiry_request_id' => $inquiryRequestId]);

        // VA sudah dibayar
        if ($vaPayment->isPaid()) {
            $response = $this->buildInquiryErrorResponse(
                responseCode: '4042414',
                responseMessage: 'Paid Bill',
                inquiryReason: ['english' => 'Already Paid', 'indonesia' => 'Sudah Dibayar'],
                payload: $payload,
                vaPayment: $vaPayment,
            );
            $this->saveLog($vaPayment->id, 'bca', 'inquiry', $payload, $response, false, 'Already paid');
            return $response;
        }

        // VA sudah expired
        if ($vaPayment->isExpired()) {
            $response = $this->buildInquiryErrorResponse(
                responseCode: '4042419',
                responseMessage: 'Invalid Bill/Virtual Account',
                inquiryReason: ['english' => 'VA Expired', 'indonesia' => 'VA Sudah Kadaluarsa'],
                payload: $payload,
                vaPayment: $vaPayment,
            );
            $this->saveLog($vaPayment->id, 'bca', 'inquiry', $payload, $response, false, 'VA expired');
            return $response;
        }

        // VA valid — kirim detail tagihan ke BCA
        $invoice  = $vaPayment->invoice;
        $response = [
            'responseCode'       => '2002400',
            'responseMessage'    => 'Successful',
            'virtualAccountData' => [
                'inquiryStatus' => '00',
                'inquiryReason' => [
                    'english'   => 'Success',
                    'indonesia' => 'Sukses',
                ],
                'partnerServiceId'   => $vaPayment->partner_service_id,
                'customerNo'         => $vaPayment->customer_no,
                'virtualAccountNo'   => $vaPayment->va_number,
                'virtualAccountName' => $vaPayment->va_name,
                'inquiryRequestId'   => $inquiryRequestId,
                'totalAmount'        => [
                    'value'    => number_format($vaPayment->amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'subCompany'  => '00000',
                'billDetails' => [
                    [
                        'billNo'          => $invoice->invoice_number,
                        'billDescription' => [
                            'english'   => 'Subscription - ' . $invoice->plan->name,
                            'indonesia' => 'Langganan - ' . $invoice->plan->name,
                        ],
                        'billSubCompany' => '00000',
                        'billAmount'     => [
                            'value'    => number_format($vaPayment->amount, 2, '.', ''),
                            'currency' => 'IDR',
                        ],
                    ],
                ],
                'freeTexts' => [
                    [
                        'english'   => 'App Absensi - ' . $invoice->plan->name,
                        'indonesia' => 'Aplikasi Absensi - ' . $invoice->plan->name,
                    ],
                    [
                        'english'   => 'Invoice: ' . $invoice->invoice_number,
                        'indonesia' => 'Invoice: ' . $invoice->invoice_number,
                    ],
                ],
            ],
        ];

        $this->saveLog($vaPayment->id, 'bca', 'inquiry', $payload, $response, true);
        return $response;
    }

    // ============================================================
    // HANDLE PAYMENT FLAG dari BCA (webhook masuk)
    // ============================================================

    /**
     * Proses payment flag dari BCA.
     * BCA memberitahu: "Customer sudah bayar VA ini."
     * Kita konfirmasi lalu aktifkan subscription dalam 1 DB transaction.
     *
     * @param  array $payload  Request body dari BCA
     * @return array           Response yang dikirim balik ke BCA
     */
    public function handleBcaPaymentFlag(array $payload): array
    {
        $vaNumber         = trim($payload['virtualAccountNo'] ?? '');
        $paymentRequestId = $payload['paymentRequestId'] ?? '';
        $referenceNo      = $payload['referenceNo'] ?? '';

        $vaPayment = VaPayment::where('va_number', $vaNumber)
            ->where('bank', 'bca')
            ->with(['invoice.subscription'])
            ->first();

        // VA tidak ditemukan
        if (! $vaPayment) {
            $response = [
                'responseCode'       => '4042512',
                'responseMessage'    => 'Invalid Bill/Virtual Account [VA not found]',
                'virtualAccountData' => [
                    'paymentFlagStatus' => '01',
                    'paymentFlagReason' => [
                        'english'   => 'VA Not Found',
                        'indonesia' => 'VA Tidak Ditemukan',
                    ],
                ],
            ];
            $this->saveLog(null, 'bca', 'payment', $payload, $response, false, 'VA not found');
            return $response;
        }

        // Sudah pernah dibayar — idempotent, return 00 sesuai spec BCA
        if ($vaPayment->isPaid()) {
            $response = [
                'responseCode'       => '4042518',
                'responseMessage'    => 'Inconsistent Request',
                'virtualAccountData' => [
                    'paymentFlagStatus' => '00',
                    'paymentFlagReason' => [
                        'english'   => 'Already Processed',
                        'indonesia' => 'Sudah Diproses',
                    ],
                    'partnerServiceId'   => $vaPayment->partner_service_id,
                    'customerNo'         => $vaPayment->customer_no,
                    'virtualAccountNo'   => $vaPayment->va_number,
                    'virtualAccountName' => $vaPayment->va_name,
                    'paymentRequestId'   => $paymentRequestId,
                    'paidAmount'         => [
                        'value'    => number_format($vaPayment->amount, 2, '.', ''),
                        'currency' => 'IDR',
                    ],
                ],
            ];
            $this->saveLog($vaPayment->id, 'bca', 'payment', $payload, $response, true);
            return $response;
        }

        // Proses pembayaran dalam 1 DB transaction
        // Urutan: VA lunas → Invoice lunas → Subscription aktif
        DB::transaction(function () use ($vaPayment, $paymentRequestId, $referenceNo) {
            $vaPayment->markAsPaid($paymentRequestId, $referenceNo);
            $invoice = $vaPayment->invoice;
            $invoice->markAsPaid();
            app(SubscriptionService::class)->activateFromInvoice($invoice);
        });

        $vaPayment->refresh();

        $response = [
            'responseCode'       => '2002500',
            'responseMessage'    => 'Successful',
            'virtualAccountData' => [
                'paymentFlagStatus' => '00',
                'paymentFlagReason' => [
                    'english'   => 'Success',
                    'indonesia' => 'Sukses',
                ],
                'partnerServiceId'   => $vaPayment->partner_service_id,
                'customerNo'         => $vaPayment->customer_no,
                'virtualAccountNo'   => $vaPayment->va_number,
                'virtualAccountName' => $vaPayment->va_name,
                'paymentRequestId'   => $paymentRequestId,
                'paidAmount'         => [
                    'value'    => number_format($vaPayment->amount, 2, '.', ''),
                    'currency' => 'IDR',
                ],
                'referenceNo' => $referenceNo,
            ],
        ];

        $this->saveLog($vaPayment->id, 'bca', 'payment', $payload, $response, true);
        return $response;
    }

    // ============================================================
    // CEK STATUS VA ke BCA (kita yang aktif tanya)
    // ============================================================

    /**
     * Tanya status pembayaran VA ke BCA secara aktif.
     * Opsional — pakai ini jika payment flag tidak kunjung masuk.
     */
    public function checkBcaStatus(VaPayment $vaPayment): array
    {
        $cfg         = $this->bcaConfig();
        $accessToken = $this->getBcaAccessToken();
        $timestamp   = now()->format('Y-m-d\TH:i:sP');
        $externalId  = $this->generateExternalId();
        $relativeUrl = '/openapi/v1.0/transfer-va/inquiry-status';

        $payload = [
            'partnerServiceId' => $vaPayment->partner_service_id,
            'customerNo'       => $vaPayment->customer_no,
            'virtualAccountNo' => $vaPayment->va_number,
            'inquiryRequestId' => $vaPayment->inquiry_request_id,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization'  => 'Bearer ' . $accessToken,
                'Content-Type'   => 'application/json',
                'X-TIMESTAMP'    => $timestamp,
                'X-SIGNATURE'    => $this->generateSymmetricSignature(
                    method: 'POST',
                    relativeUrl: $relativeUrl,
                    accessToken: $accessToken,
                    body: $payload,
                    timestamp: $timestamp,
                    clientSecret: $cfg['client_secret']
                ),
                'X-PARTNER-ID'   => $cfg['partner_service_id'],
                'X-EXTERNAL-ID'  => $externalId,
                'CHANNEL-ID'     => $cfg['channel_id'],
            ])->post($cfg['base_url'] . $relativeUrl, $payload);

            $result = $response->json();
        } catch (\Exception $e) {
            throw new \Exception('Gagal cek status ke BCA: ' . $e->getMessage());
        }

        $this->saveLog($vaPayment->id, 'bca', 'inquiry_status', $payload, $result, true);
        return $result;
    }

    // ============================================================
    // EXPIRE VA (dipanggil scheduler)
    // ============================================================

    /**
     * Expire semua VA pending yang melewati expired_at.
     * Return jumlah VA yang di-expire.
     */
    public function expireAll(): int
    {
        return VaPayment::query()
            ->where('status', 'pending')
            ->where('expired_at', '<', now())
            ->update(['status' => 'expired']);
    }

    // ============================================================
    // PRIVATE — HELPERS
    // ============================================================

    /**
     * Generate customerNo unik per invoice.
     * Format: invoice_id di-pad ke 20 digit dengan leading zero.
     */
    private function generateCustomerNo(SubscriptionInvoice $invoice): string
    {
        return str_pad((string) $invoice->id, 20, '0', STR_PAD_LEFT);
    }

    /**
     * Generate X-EXTERNAL-ID: numeric string, unik per hari, max 36 char.
     */
    private function generateExternalId(): string
    {
        return now()->format('YmdHis') . rand(1000, 9999);
    }

    // ============================================================
    // PRIVATE — ACCESS TOKEN
    // ============================================================

    /**
     * Ambil BCA access token.
     * Di-cache 890 detik (token BCA berlaku 900 detik) supaya tidak
     * request ulang setiap ada API call.
     *
     * Set CACHE_STORE=redis di .env untuk production agar cache
     * shared di semua worker/proses.
     */
    private function getBcaAccessToken(): string
    {
        return Cache::remember('bca_access_token', 890, function () {
            return $this->fetchBcaToken();
        });
    }

    private function fetchBcaToken(): string
    {
        $cfg       = $this->bcaConfig();
        $timestamp = now()->format('Y-m-d\TH:i:sP');

        $stringToSign = $cfg['client_id'] . '|' . $timestamp;
        $signature    = $this->generateAsymmetricSignature($stringToSign, $cfg['private_key']);

        $response = Http::withHeaders([
            'X-TIMESTAMP'  => $timestamp,
            'X-CLIENT-KEY' => $cfg['client_id'],
            'X-SIGNATURE'  => $signature,
            'Content-Type' => 'application/json',
        ])->post($cfg['base_url'] . '/openapi/v1.0/access-token/b2b', [
            'grantType' => 'client_credentials',
        ]);

        $token = $response->json('accessToken');

        if (! $token) {
            Log::error('[BCA Token] Gagal ambil access token', ['response' => $response->json()]);
            throw new \Exception('Gagal ambil BCA access token. Cek BCA_CLIENT_ID & BCA_PRIVATE_KEY di .env');
        }

        return $token;
    }

    /**
     * Ambil Mandiri access token, di-cache 890 detik.
     */
    private function getMandiriAccessToken(): string
    {
        return Cache::remember('mandiri_access_token', 890, function () {
            $cfg = $this->mandiriConfig();

            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode(
                    $cfg['client_id'] . ':' . $cfg['client_secret']
                ),
            ])->post($cfg['base_url'] . '/api/oauth/token', [
                'grant_type' => 'client_credentials',
            ]);

            $token = $response->json('access_token');

            if (! $token) {
                Log::error('[Mandiri Token] Gagal ambil access token', ['response' => $response->json()]);
                throw new \Exception('Gagal ambil Mandiri access token. Cek MANDIRI_CLIENT_ID & MANDIRI_CLIENT_SECRET di .env');
            }

            return $token;
        });
    }

    // ============================================================
    // PRIVATE — SIGNATURE
    // ============================================================

    /**
     * Generate Signature Asymmetric untuk get access token BCA SNAP.
     *
     * Algoritma : SHA256withRSA → encode Base64
     * StringToSign : clientId + "|" + X-TIMESTAMP
     *
     * Private key bisa dari 2 sumber (otomatis via config/payment.php):
     *   1. String PEM di .env (BCA_PRIVATE_KEY)
     *      → \n di .env adalah literal string, perlu di-unescape ke newline sungguhan
     *   2. File PEM di storage/keys/bca_private.pem
     *      → Langsung terbaca tanpa perlu str_replace
     */
    private function generateAsymmetricSignature(string $stringToSign, string $privateKeyPem): string
    {
        // Unescape \n dari .env menjadi newline sungguhan
        // Tidak berpengaruh jika key sudah dari file (sudah punya newline asli)
        $privateKeyPem = str_replace('\n', "\n", $privateKeyPem);

        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (! $privateKey) {
            throw new \Exception(
                'BCA Private Key tidak valid. ' .
                    'Pastikan BCA_PRIVATE_KEY di .env sudah benar (format PEM), ' .
                    'atau file storage/keys/bca_private.pem tersedia dan dapat dibaca.'
            );
        }

        openssl_sign($stringToSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    /**
     * Generate Signature Symmetric untuk semua API request BCA SNAP.
     *
     * Algoritma : HMAC-SHA512 → encode Base64
     * StringToSign:
     *   HTTPMethod + ":" + RelativeUrl + ":" + AccessToken + ":"
     *   + Lowercase(HexEncode(SHA256(MinifyJson(RequestBody)))) + ":" + Timestamp
     */
    private function generateSymmetricSignature(
        string $method,
        string $relativeUrl,
        string $accessToken,
        array  $body,
        string $timestamp,
        string $clientSecret
    ): string {
        $minifiedBody = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hashedBody   = strtolower(hash('sha256', $minifiedBody));

        $stringToSign = implode(':', [
            strtoupper($method),
            $relativeUrl,
            $accessToken,
            $hashedBody,
            $timestamp,
        ]);

        return base64_encode(hash_hmac('sha512', $stringToSign, $clientSecret, true));
    }

    // ============================================================
    // PRIVATE — RESPONSE BUILDER
    // ============================================================

    /**
     * Bangun response error inquiry BCA dengan struktur yang konsisten.
     */
    private function buildInquiryErrorResponse(
        string     $responseCode,
        string     $responseMessage,
        array      $inquiryReason,
        array      $payload,
        ?VaPayment $vaPayment = null,
    ): array {
        $data = [
            'inquiryStatus' => '01',
            'inquiryReason' => $inquiryReason,
        ];

        if ($vaPayment) {
            $data = array_merge($data, [
                'partnerServiceId'   => $vaPayment->partner_service_id,
                'customerNo'         => $vaPayment->customer_no,
                'virtualAccountNo'   => $vaPayment->va_number,
                'virtualAccountName' => $vaPayment->va_name,
                'inquiryRequestId'   => $payload['inquiryRequestId'] ?? '',
            ]);
        }

        return [
            'responseCode'       => $responseCode,
            'responseMessage'    => $responseMessage,
            'virtualAccountData' => $data,
        ];
    }

    // ============================================================
    // PRIVATE — LOG
    // ============================================================

    /**
     * Simpan semua hit webhook ke tabel va_payment_logs untuk audit trail.
     * Dibungkus try-catch supaya kegagalan log tidak menyebabkan
     * response ke bank ikut gagal.
     */
    private function saveLog(
        ?int   $vaPaymentId,
        string $bank,
        string $eventType,
        array  $request,
        array  $response,
        bool   $isSuccess,
        string $errorMessage = ''
    ): void {
        try {
            VaPaymentLog::create([
                'va_payment_id'      => $vaPaymentId,
                'bank'               => $bank,
                'event_type'         => $eventType,
                'ip_address'         => request()->ip(),
                'request_payload'    => $request,
                'response_payload'   => $response,
                'response_http_code' => $isSuccess ? 200 : 400,
                'is_success'         => $isSuccess,
                'error_message'      => $errorMessage ?: null,
                'received_at'        => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('[VA Log] Gagal simpan log: ' . $e->getMessage(), [
                'bank'       => $bank,
                'event_type' => $eventType,
            ]);
        }
    }
}
