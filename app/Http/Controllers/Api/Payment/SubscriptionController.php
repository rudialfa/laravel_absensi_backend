<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Subscription\SelectPlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\InvoiceService;
use App\Services\SubscriptionService;
use App\Services\VaPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private InvoiceService      $invoiceService,
        private VaPaymentService    $vaPaymentService,
    ) {}

    // ============================================================
    // GET /api/v1/subscriptions/plans
    // ============================================================
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn($plan) => [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'slug'          => $plan->slug,
                'description'   => $plan->description,
                'duration_days' => $plan->duration_days,
                'price'         => (float) $plan->price,
                'price_label'   => $plan->is_free
                    ? 'Gratis'
                    : 'Rp ' . number_format($plan->price, 0, ',', '.'),
            'is_free'      => $plan->is_free,
            'is_popular'   => $plan->is_popular,
            'saving_label' => $plan->saving_label,
            ]);

        return response()->json(['success' => true, 'data' => $plans]);
    }

    // ============================================================
    // POST /api/v1/subscriptions/trial
    // ============================================================
    public function startTrial(Request $request): JsonResponse
    {
        $user    = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum terhubung ke perusahaan.',
            ], 422);
        }

        if (! $user->isBillingManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya penanggung jawab billing yang dapat memulai trial. Silakan hubungi admin perusahaan Anda.',
            ], 403);
        }

        try {
            $subscription = $this->subscriptionService->startTrial($company);

            return response()->json([
                'success' => true,
                'message' => 'Trial berhasil dimulai.',
                'data'    => $this->_formatSubscription($subscription),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ============================================================
    // GET /api/v1/subscriptions/status
    // ============================================================
    public function status(Request $request): JsonResponse
    {
        $user    = $request->user();
        $company = $user->company;

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung ke perusahaan.',
            ], 422);
        }

        $status = $this->subscriptionService->getStatus($company);

        // Detail tagihan/VA cuma relevan buat yang bisa bayar (billing manager
        // company ini — misal hr/ustadz/teacher/doctor sesuai tipe company).
        // Role lain (employee, santri, student, dst) tidak perlu tahu nomor VA.
        $pendingInvoiceData = null;

        if ($user->isBillingManager()) {
            $pendingInvoice = $this->invoiceService->getPendingInvoice($company);

            if ($pendingInvoice) {
                $pendingInvoiceData = [
                    'invoice_number' => $pendingInvoice->invoice_number,
                    'total_amount'   => (float) $pendingInvoice->total_amount,
                    'due_at'         => $pendingInvoice->due_at->toIso8601String(),
                    'va'             => $pendingInvoice->vaPayment ? [
                        'bank'       => $pendingInvoice->vaPayment->bank,
                        'va_number'  => $pendingInvoice->vaPayment->va_number,
                        'va_name'    => $pendingInvoice->vaPayment->va_name,
                        'amount'     => (float) $pendingInvoice->vaPayment->amount,
                        'expired_at' => $pendingInvoice->vaPayment->expired_at?->toIso8601String(),
                    ] : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($status, [
                'is_billing_manager' => $user->isBillingManager(),
                'pending_invoice'    => $pendingInvoiceData,
            ]),
        ]);
    }

    // ============================================================
    // POST /api/v1/subscriptions/select
    // Pilih paket → buat invoice → buat VA Midtrans → return nomor VA
    // ============================================================
    public function selectPlan(SelectPlanRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isBillingManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya penanggung jawab billing yang dapat memperpanjang langganan. Silakan hubungi admin perusahaan Anda.',
            ], 403);
        }

        $company = $user->company;

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung ke perusahaan.',
            ], 422);
        }

        $plan = SubscriptionPlan::where('slug', $request->plan_slug)
            ->where('is_active', true)
            ->where('is_free', false)
            ->firstOrFail();

        try {
            $invoice   = $this->invoiceService->create($company, $plan, $request->discount_code);
            $vaPayment = $this->vaPaymentService->createVa($invoice, $request->bank);

            return response()->json([
                'success' => true,
                'message' => 'Invoice berhasil dibuat. Silakan lakukan pembayaran.',
                'data'    => [
                    'invoice' => [
                        'invoice_number'  => $invoice->invoice_number,
                        'plan_name'       => $plan->name,
                        'subtotal'        => (float) $invoice->subtotal,
                        'discount_amount' => (float) $invoice->discount_amount,
                        'total_amount'    => (float) $invoice->total_amount,
                        'due_at'          => $invoice->due_at->toIso8601String(),
                    ],
                    'va' => [
                        'bank'       => $vaPayment->bank,
                        'va_number'  => $vaPayment->va_number,
                        'va_name'    => $vaPayment->va_name,
                        'amount'     => (float) $vaPayment->amount,
                        'expired_at' => $vaPayment->expired_at?->toIso8601String(),
                    ],
                    'how_to_pay' => $this->_howToPay($vaPayment->bank, $vaPayment->va_number),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ============================================================
    // POST /api/v1/subscriptions/check-va
    // ============================================================
    public function checkVa(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isBillingManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya penanggung jawab billing yang dapat mengecek status pembayaran.',
            ], 403);
        }

        $company        = $user->company;
        $pendingInvoice = $this->invoiceService->getPendingInvoice($company);

        if (! $pendingInvoice || ! $pendingInvoice->vaPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada transaksi VA yang aktif.',
            ], 404);
        }

        $vaPayment = $pendingInvoice->vaPayment;

        if ($vaPayment->isPaid()) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => 'paid', 'message' => 'Pembayaran sudah dikonfirmasi.'],
            ]);
        }

        try {
            $result = $this->vaPaymentService->checkStatus($vaPayment);

            return response()->json([
                'success' => true,
                'data'    => [
                    'status'           => $vaPayment->fresh()->status,
                    'midtrans_result'  => $result,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ============================================================
    // GET /api/v1/subscriptions/invoices
    // ============================================================
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isBillingManager()) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya penanggung jawab billing yang dapat melihat histori invoice.',
            ], 403);
        }

        $company  = $user->company;
        $invoices = $this->invoiceService->getHistory($company);

        $invoices->getCollection()->transform(fn($inv) => [
            'invoice_number'  => $inv->invoice_number,
            'plan_name'       => $inv->plan->name,
            'subtotal'        => (float) $inv->subtotal,
            'discount_amount' => (float) $inv->discount_amount,
            'total_amount'    => (float) $inv->total_amount,
            'status'          => $inv->status,
            'issued_at'       => $inv->issued_at->toIso8601String(),
            'paid_at'         => $inv->paid_at?->toIso8601String(),
            'va'              => $inv->vaPayment ? [
                'bank'      => $inv->vaPayment->bank,
                'va_number' => $inv->vaPayment->va_number,
                'status'    => $inv->vaPayment->status,
            ] : null,
        ]);

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function _formatSubscription($subscription): array
    {
        return [
            'status'         => $subscription->status,
            'is_active'      => $subscription->isActive(),
            'days_remaining' => $subscription->daysRemaining(),
            'started_at'     => $subscription->started_at->toIso8601String(),
            'expires_at'     => $subscription->expires_at->toIso8601String(),
            'plan'           => [
                'name'          => $subscription->plan->name,
                'slug'          => $subscription->plan->slug,
                'duration_days' => $subscription->plan->duration_days,
            ],
        ];
    }

    private function _howToPay(string $bank, string $vaNumber): array
    {
        return match ($bank) {
            'bca' => [
                'ATM BCA'      => "1. Pilih Transaksi Lainnya → Transfer → Ke Rek BCA Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
                'mBanking BCA' => "1. Login myBCA → Transfer → Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
            ],
            'mandiri' => [
                'ATM Mandiri'   => "1. Pilih Bayar/Beli → Lainnya → Multi Payment\n2. Masukkan kode perusahaan lalu nomor VA/Bill Key: {$vaNumber}\n3. Konfirmasi pembayaran",
                'Livin Mandiri' => "1. Login Livin → Pembayaran → Multi Payment\n2. Masukkan nomor VA/Bill Key: {$vaNumber}\n3. Konfirmasi pembayaran",
            ],
            'bni' => [
                'ATM BNI'      => "1. Pilih Menu Lain → Transfer → Virtual Account Billing\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
                'BNI Mobile'   => "1. Login BNI Mobile Banking → Transfer → Virtual Account Billing\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
            ],
            'bri' => [
                'ATM BRI'   => "1. Pilih Transaksi Lain → Pembayaran → Lainnya → BRIVA\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
                'BRImo'     => "1. Login BRImo → Pembayaran → BRIVA\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
            ],
            'cimb' => [
                'OCTO Mobile' => "1. Login OCTO Mobile → Pembayaran → Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
                'ATM CIMB'    => "1. Pilih Pembayaran → Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
            ],
            default => [],
        };
    }
}
