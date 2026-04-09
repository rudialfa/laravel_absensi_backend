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
    // Tampilkan semua paket yang tersedia (public, tidak perlu login)
    // ============================================================
 
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($plan) => [
                'id'            => $plan->id,
                'name'          => $plan->name,
                'slug'          => $plan->slug,
                'description'   => $plan->description,
                'duration_days' => $plan->duration_days,
                'price'         => (float) $plan->price,
                'price_label'   => $plan->is_free
                    ? 'Gratis'
                    : 'Rp ' . number_format($plan->price, 0, ',', '.'),
                'is_free'       => $plan->is_free,
            ]);
 
        return response()->json([
            'success' => true,
            'data'    => $plans,
        ]);
    }
 
    // ============================================================
    // POST /api/v1/subscriptions/trial
    // Mulai trial gratis — dipanggil saat company pertama register
    // ============================================================
 
    public function startTrial(Request $request): JsonResponse
    {
        $company = $request->user()->company;
 
        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum terhubung ke perusahaan.',
            ], 422);
        }
 
        try {
            $subscription = $this->subscriptionService->startTrial($company);
 
            return response()->json([
                'success' => true,
                'message' => 'Trial 7 hari berhasil dimulai.',
                'data'    => $this->formatSubscription($subscription),
            ]);
 
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
 
    // ============================================================
    // GET /api/v1/subscriptions/status
    // Status subscription aktif milik company
    // ============================================================
 
    public function status(Request $request): JsonResponse
    {
        $company = $request->user()->company;
 
        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung ke perusahaan.',
            ], 422);
        }
 
        $status = $this->subscriptionService->getStatus($company);
 
        // Cek apakah ada invoice pending yang menunggu pembayaran
        $pendingInvoice = $this->invoiceService->getPendingInvoice($company);
 
        return response()->json([
            'success' => true,
            'data'    => array_merge($status, [
                'pending_invoice' => $pendingInvoice ? [
                    'invoice_number' => $pendingInvoice->invoice_number,
                    'total_amount'   => (float) $pendingInvoice->total_amount,
                    'due_at'         => $pendingInvoice->due_at->toIso8601String(),
                    'va'             => $pendingInvoice->vaPayment ? [
                        'bank'      => $pendingInvoice->vaPayment->bank,
                        'va_number' => $pendingInvoice->vaPayment->va_number,
                        'va_name'   => $pendingInvoice->vaPayment->va_name,
                        'amount'    => (float) $pendingInvoice->vaPayment->amount,
                        'expired_at'=> $pendingInvoice->vaPayment->expired_at?->toIso8601String(),
                    ] : null,
                ] : null,
            ]),
        ]);
    }
 
    // ============================================================
    // POST /api/v1/subscriptions/select
    // Pilih paket → buat invoice → buat VA → return nomor VA
    // ============================================================
 
    public function selectPlan(SelectPlanRequest $request): JsonResponse
    {
        $company = $request->user()->company;
 
        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak terhubung ke perusahaan.',
            ], 422);
        }
 
        $plan = SubscriptionPlan::where('slug', $request->plan_slug)
            ->where('is_active', true)
            ->where('is_free', false) // paket gratis tidak bisa dipilih manual
            ->firstOrFail();
 
        try {
            // 1. Buat invoice
            $invoice = $this->invoiceService->create(
                company:      $company,
                plan:         $plan,
                discountCode: $request->discount_code,
            );
 
            // 2. Buat VA sesuai bank yang dipilih
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
                    'how_to_pay' => $this->howToPay($vaPayment->bank, $vaPayment->va_number),
                ],
            ]);
 
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
 
    // ============================================================
    // POST /api/v1/subscriptions/check-va
    // Cek status VA secara manual (jika payment flag belum masuk)
    // ============================================================
 
    public function checkVa(Request $request): JsonResponse
    {
        $company = $request->user()->company;
 
        $pendingInvoice = $this->invoiceService->getPendingInvoice($company);
 
        if (! $pendingInvoice || ! $pendingInvoice->vaPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada transaksi VA yang aktif.',
            ], 404);
        }
 
        $vaPayment = $pendingInvoice->vaPayment;
 
        // Jika sudah lunas di DB, return langsung
        if ($vaPayment->isPaid()) {
            return response()->json([
                'success' => true,
                'data'    => ['status' => 'paid', 'message' => 'Pembayaran sudah dikonfirmasi.'],
            ]);
        }
 
        try {
            // Tanya langsung ke BCA
            $result = $this->vaPaymentService->checkBcaStatus($vaPayment);
 
            return response()->json([
                'success' => true,
                'data'    => [
                    'status'      => $vaPayment->fresh()->status,
                    'bank_result' => $result,
                ],
            ]);
 
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
 
    // ============================================================
    // GET /api/v1/subscriptions/invoices
    // Histori semua invoice milik company
    // ============================================================
 
    public function invoices(Request $request): JsonResponse
    {
        $company  = $request->user()->company;
        $invoices = $this->invoiceService->getHistory($company);
 
        $invoices->getCollection()->transform(fn ($inv) => [
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
 
        return response()->json([
            'success' => true,
            'data'    => $invoices,
        ]);
    }
 
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================
 
    private function formatSubscription($subscription): array
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
 
    private function howToPay(string $bank, string $vaNumber): array
    {
        return match ($bank) {
            'bca' => [
                'ATM BCA'     => "1. Pilih Transaksi Lainnya → Transfer → Ke Rek BCA Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
                'mBanking BCA'=> "1. Login myBCA → Transfer → Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
                'KlikBCA'     => "1. Login KlikBCA → Transfer Dana → Transfer ke BCA Virtual Account\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi dan selesaikan pembayaran",
            ],
            'mandiri' => [
                'ATM Mandiri'    => "1. Pilih Bayar/Beli → Lainnya → Multi Payment\n2. Masukkan kode perusahaan lalu nomor VA: {$vaNumber}\n3. Konfirmasi pembayaran",
                'Livin Mandiri'  => "1. Login Livin → Pembayaran → Multi Payment\n2. Masukkan nomor VA: {$vaNumber}\n3. Konfirmasi pembayaran",
            ],
            default => [],
        };
    }
}
