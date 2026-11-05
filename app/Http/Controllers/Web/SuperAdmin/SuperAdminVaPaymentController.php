<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VaPayment;
use App\Services\Midtrans\Midtrans;
use App\Services\SubscriptionService;
use App\Services\VaPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Transaction;

/**
 * Monitoring Virtual Account (dibuat otomatis lewat Midtrans saat tenant memilih paket,
 * atau dari menu Invoice). VA tidak dibuat/diedit manual di sini — hanya dipantau & ditindaklanjuti.
 */
class SuperAdminVaPaymentController extends Controller
{
    public const STATUSES = [
        'pending'   => 'Menunggu bayar',
        'paid'      => 'Lunas',
        'expired'   => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    public function __construct(
        private SubscriptionService $subscriptionService,
        private VaPaymentService $vaPaymentService,
    ) {}

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $vaPayments = $this->filteredQuery($request)
            ->with(['company:id,name', 'invoice:id,invoice_number,status'])
            ->withCount('logs')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $summary = $this->filteredQuery($request)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(amount) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // VA pending yang masa berlakunya sudah lewat (webhook expire belum masuk)
        $staleCount = VaPayment::expired()->count();

        // Status VA ≠ status invoice (indikasi data tidak sinkron)
        $mismatchCount = VaPayment::join('subscription_invoices', 'subscription_invoices.id', '=', 'va_payments.invoice_id')
            ->whereColumn('va_payments.status', '!=', 'subscription_invoices.status')
            ->count();

        return view('pages.superadmin.va-payments.index', [
            'vaPayments'    => $vaPayments,
            'summary'       => $summary,
            'statuses'      => self::STATUSES,
            'banks'         => VaPayment::select('bank')->distinct()->orderBy('bank')->pluck('bank'),
            'staleCount'    => $staleCount,
            'mismatchCount' => $mismatchCount,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $vaPayment = VaPayment::with(['company', 'invoice.plan', 'invoice.subscription'])->findOrFail($id);

        $logs = $vaPayment->logs()->orderByDesc('received_at')->get();

        $history = AuditLog::with('user:id,name')
            ->where('subject_type', VaPayment::class)
            ->where('subject_id', $vaPayment->id)
            ->latest()
            ->get();

        // bank_response tersimpan sebagai json_encode(...) + cast array → bisa string atau array
        $bankResponse = $vaPayment->bank_response;
        if (is_string($bankResponse)) {
            $bankResponse = json_decode($bankResponse, true) ?? $bankResponse;
        }

        return view('pages.superadmin.va-payments.show', [
            'vaPayment'    => $vaPayment,
            'logs'         => $logs,
            'history'      => $history,
            'bankResponse' => $bankResponse,
            'statuses'     => self::STATUSES,
            'isStale'      => $vaPayment->isPending() && $vaPayment->expired_at && $vaPayment->expired_at->isPast(),
        ]);
    }

    // =========================================================
    // CEK STATUS KE MIDTRANS
    // =========================================================
    public function checkStatus($id)
    {
        $vaPayment = VaPayment::with('invoice')->findOrFail($id);

        if (!$vaPayment->invoice) {
            return back()->with('error', 'VA ini tidak terhubung ke invoice.');
        }

        if ($vaPayment->isPaid() || $vaPayment->invoice->isPaid()) {
            return back()->with('success', 'VA/invoice sudah lunas, tidak perlu dicek ulang.');
        }

        try {
            $result = $this->vaPaymentService->checkStatus($vaPayment);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal cek ke Midtrans: ' . $e->getMessage());
        }

        AuditLog::record('check_va_status', $vaPayment, "Cek status VA {$vaPayment->va_number} ke Midtrans: " . ($result['transaction_status'] ?? '-'));

        $fresh = $vaPayment->fresh();

        return back()->with('success', 'Status di Midtrans: ' . ($result['transaction_status'] ?? '-')
            . '. Status VA sekarang: ' . (self::STATUSES[$fresh->status] ?? $fresh->status) . '.');
    }

    // =========================================================
    // TANDAI LUNAS (webhook tidak masuk tapi dana sudah diterima)
    // =========================================================
    public function markPaid(Request $request, $id)
    {
        $vaPayment = VaPayment::with('invoice.plan')->findOrFail($id);

        $data = $request->validate([
            'reference' => 'nullable|string|max:100',
            'reason'    => 'required|string|max:500',
        ], [], ['reference' => 'referensi', 'reason' => 'alasan']);

        if ($vaPayment->isPaid()) {
            return back()->with('error', 'VA ini sudah berstatus lunas.');
        }

        $invoice = $vaPayment->invoice;

        if (!$invoice) {
            return back()->with('error', 'VA ini tidak terhubung ke invoice, tidak bisa diproses.');
        }

        $invoiceWasPaid = $invoice->isPaid();

        DB::transaction(function () use ($vaPayment, $invoice, $data, $invoiceWasPaid) {
            $vaPayment->markAsPaid(null, $data['reference'] ?: null);

            if (!$invoiceWasPaid) {
                $invoice->notes = trim(($invoice->notes ? $invoice->notes . "\n" : '')
                    . '[Lunas manual via VA ' . now()->format('d/m/Y H:i') . '] '
                    . ($data['reference'] ? "Ref: {$data['reference']} · " : '') . $data['reason']);
                $invoice->save();
                $invoice->markAsPaid();
            }
        });

        // FIX: pakai alur yang SAMA dengan webhook (perpanjang sesuai paket di invoice + notifikasi).
        // Sebelumnya controller ini menghitung perpanjangan sendiri dari paket langganan (bisa beda paket)
        // dan tidak mengirim notifikasi. Kalau invoice sudah lunas sebelumnya, langganan TIDAK diperpanjang lagi.
        $message = 'VA ditandai lunas.';
        if (!$invoiceWasPaid) {
            $subscription = $this->subscriptionService->activateFromInvoice($invoice->fresh());
            $message = "VA & invoice lunas. Langganan aktif sampai {$subscription->expires_at->format('d M Y')}.";
        }

        AuditLog::record('manual_mark_va_paid', $vaPayment, "VA {$vaPayment->va_number} (" . strtoupper($vaPayment->bank) . ") ditandai lunas manual. Alasan: {$data['reason']}", $data);

        return back()->with('success', $message);
    }

    // =========================================================
    // TUTUP VA (tidak bisa dibayar lagi)
    // =========================================================
    public function close(Request $request, $id)
    {
        $vaPayment = VaPayment::with('invoice')->findOrFail($id);

        $data = $request->validate([
            'reason'         => 'required|string|max:500',
            'cancel_invoice' => 'nullable|boolean',
        ], [], ['reason' => 'alasan']);

        if (!$vaPayment->isPending()) {
            return back()->with('error', 'Hanya VA yang masih menunggu bayar yang bisa ditutup.');
        }

        $warning = null;

        try {
            new Midtrans();
            Transaction::expire($vaPayment->invoice->invoice_number ?? '');
        } catch (\Throwable $e) {
            Log::warning('Gagal expire VA di Midtrans', ['va' => $vaPayment->id, 'error' => $e->getMessage()]);
            $warning = 'VA di Midtrans tidak berhasil ditutup otomatis (' . $e->getMessage() . '). Cek dashboard Midtrans.';
        }

        DB::transaction(function () use ($vaPayment, $request) {
            $vaPayment->markAsCancelled();

            if ($request->boolean('cancel_invoice') && $vaPayment->invoice && $vaPayment->invoice->isPending()) {
                $vaPayment->invoice->update(['status' => 'cancelled']);
            }
        });

        AuditLog::record('close_va', $vaPayment, "VA {$vaPayment->va_number} ditutup. Alasan: {$data['reason']}", [
            'cancel_invoice' => $request->boolean('cancel_invoice'),
        ]);

        $redirect = back()->with('success', 'VA ditutup.' . ($request->boolean('cancel_invoice') ? ' Invoice ikut dibatalkan.' : ' Invoice masih menunggu bayar (bisa dibuatkan VA baru dari menu Invoice).'));

        return $warning ? $redirect->with('warning', $warning) : $redirect;
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function filteredQuery(Request $request)
    {
        $query = VaPayment::query();

        if ($request->filled('bank')) {
            $query->where('bank', $request->bank);
        }

        if ($request->filled('status')) {
            $request->status === 'stale'
                ? $query->expired()
                : $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('va_number', 'like', $like)
                    ->orWhere('va_name', 'like', $like)
                    ->orWhere('bank_reference_no', 'like', $like)
                    ->orWhereHas('company', fn($c) => $c->where('name', 'like', $like))
                    ->orWhereHas('invoice', fn($i) => $i->where('invoice_number', 'like', $like));
            });
        }

        return $query;
    }
}
