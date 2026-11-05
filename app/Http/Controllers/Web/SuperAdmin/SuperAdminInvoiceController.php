<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionDiscount;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Services\Midtrans\Midtrans;
use App\Services\SubscriptionService;
use App\Services\VaPaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Midtrans\Transaction;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminInvoiceController extends Controller
{
    public const STATUSES = [
        'pending'   => 'Menunggu bayar',
        'paid'      => 'Lunas',
        'expired'   => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    /** Bank VA yang didukung CreateVAService (Midtrans) */
    public const BANKS = ['bca' => 'BCA', 'mandiri' => 'Mandiri', 'bni' => 'BNI', 'bri' => 'BRI'];

    public function __construct(
        private SubscriptionService $subscriptionService,
        private VaPaymentService $vaPaymentService,
    ) {}

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $query = $this->filteredQuery($request)->with(['company:id,name,type', 'plan:id,name', 'vaPayment:id,invoice_id,bank,status']);

        $invoices = $query->latest('issued_at')->paginate(20)->withQueryString();

        $summary = $this->filteredQuery($request)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(total_amount) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('pages.superadmin.invoices.index', [
            'invoices'      => $invoices,
            'summary'       => $summary,
            'statuses'      => self::STATUSES,
            'plans'         => SubscriptionPlan::orderBy('sort_order')->get(['id', 'name']),
            'overdueCount'  => SubscriptionInvoice::overdue()->count(),
        ]);
    }

    /** Unduh hasil filter sebagai CSV (dibuka di Excel) */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)->with(['company:id,name', 'plan:id,name', 'discount:id,code,name', 'vaPayment:id,invoice_id,bank']);
        $fileName = 'invoice-' . now()->format('Ymd-His') . '.csv';

        AuditLog::record('export_invoices', null, 'Export invoice ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel membaca UTF-8
            fputcsv($out, ['No. Invoice', 'Tenant', 'Paket', 'Voucher', 'Subtotal', 'Diskon', 'Total', 'Status', 'Bank', 'Diterbitkan', 'Jatuh Tempo', 'Dibayar', 'Catatan'], ';');

            $query->latest('issued_at')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $inv) {
                    fputcsv($out, [
                        $inv->invoice_number,
                        $inv->company->name ?? '-',
                        $inv->plan->name ?? '-',
                        $inv->discount ? ($inv->discount->code ?? $inv->discount->name) : '',
                        (float) $inv->subtotal,
                        (float) $inv->discount_amount,
                        (float) $inv->total_amount,
                        self::STATUSES[$inv->status] ?? $inv->status,
                        $inv->vaPayment ? strtoupper($inv->vaPayment->bank) : '',
                        optional($inv->issued_at)->format('Y-m-d H:i'),
                        optional($inv->due_at)->format('Y-m-d H:i'),
                        optional($inv->paid_at)->format('Y-m-d H:i'),
                        $inv->notes,
                    ], ';');
                }
            });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // CREATE + STORE — invoice manual
    // =========================================================
    public function create(Request $request)
    {
        return view('pages.superadmin.invoices.create', [
            'companies' => Company::orderBy('name')->get(['id', 'name', 'type', 'is_active']),
            'plans'     => SubscriptionPlan::where('is_free', false)->orderBy('sort_order')->get(),
            'discounts' => SubscriptionDiscount::valid()->orderBy('name')->get(),
            'banks'     => self::BANKS,
            'presetId'  => $request->input('company_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'  => 'required|exists:companies,id',
            'plan_id'     => 'required|exists:subscription_plans,id',
            'discount_id' => 'nullable|exists:subscription_discounts,id',
            'due_hours'   => 'required|integer|min:1|max:720',
            'notes'       => 'nullable|string|max:1000',
            'create_va'   => 'nullable|boolean',
            'bank'        => 'nullable|required_if:create_va,1|in:' . implode(',', array_keys(self::BANKS)),
        ], [
            'bank.required_if' => 'Pilih bank untuk membuat VA.',
        ], [
            'company_id'  => 'tenant',
            'plan_id'     => 'paket',
            'discount_id' => 'voucher',
            'due_hours'   => 'batas bayar',
        ]);

        $company = Company::findOrFail($data['company_id']);
        $plan    = SubscriptionPlan::findOrFail($data['plan_id']);

        if ($plan->is_free) {
            throw ValidationException::withMessages(['plan_id' => 'Paket gratis tidak memerlukan invoice. Gunakan menu Langganan.']);
        }

        // Satu tenant hanya boleh punya 1 invoice pending (sama seperti alur aplikasi)
        $pending = SubscriptionInvoice::where('company_id', $company->id)->pending()->first();
        if ($pending) {
            return back()->withInput()->with('error', "Tenant ini masih punya invoice pending {$pending->invoice_number}. Selesaikan atau batalkan dulu.");
        }

        $discount = null;
        if (!empty($data['discount_id'])) {
            $discount = SubscriptionDiscount::findOrFail($data['discount_id']);

            if (!$discount->isValid()) {
                throw ValidationException::withMessages(['discount_id' => 'Voucher sudah tidak berlaku.']);
            }
            if ($discount->plan_id && (int) $discount->plan_id !== (int) $plan->id) {
                throw ValidationException::withMessages(['discount_id' => 'Voucher ini khusus paket lain.']);
            }
        }

        // Rumus SAMA dengan App\Services\InvoiceService::create()
        $invoice = DB::transaction(function () use ($company, $plan, $discount, $data) {
            $subtotal       = (float) $plan->price;
            $discountAmount = $discount ? $discount->calculateDiscount($subtotal) : 0;
            $totalAmount    = max(0, $subtotal - $discountAmount);

            $subscription = $company->subscriptions()->first() ?? CompanySubscription::create([
                'company_id' => $company->id,
                'plan_id'    => $plan->id,
                'status'     => 'expired',
                'started_at' => now(),
                'expires_at' => now(),
            ]);

            $invoice = SubscriptionInvoice::create([
                'invoice_number'  => SubscriptionInvoice::generateNumber(),
                'company_id'      => $company->id,
                'subscription_id' => $subscription->id,
                'plan_id'         => $plan->id,
                'discount_id'     => $discount?->id,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount'    => $totalAmount,
                'status'          => 'pending',
                'issued_at'       => now(),
                'due_at'          => now()->addHours((int) $data['due_hours']),
                'notes'           => $data['notes'] ?? null,
            ]);

            $discount?->incrementUsage();

            return $invoice;
        });

        AuditLog::record('create_invoice', $invoice, "Invoice {$invoice->invoice_number} dibuat manual untuk {$company->name}", [
            'total' => (float) $invoice->total_amount,
        ]);

        $redirect = redirect()->route('superadmin.invoices.show', $invoice->id)
            ->with('success', "Invoice {$invoice->invoice_number} berhasil dibuat.");

        // VA dibuat terpisah: kalau Midtrans gagal, invoice tetap tersimpan
        if ($request->boolean('create_va') && (float) $invoice->total_amount > 0) {
            try {
                $va = $this->vaPaymentService->createVa($invoice->load(['company', 'plan']), $data['bank']);
                $redirect->with('success', "Invoice {$invoice->invoice_number} dibuat. VA " . strtoupper($va->bank) . ": {$va->va_number}");
            } catch (\Throwable $e) {
                Log::error('Gagal membuat VA dari panel', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);
                $redirect->with('warning', 'Invoice dibuat, tapi VA gagal dibuat: ' . $e->getMessage() . '. Coba lagi dari tombol "Buat VA".');
            }
        }

        return $redirect;
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $invoice = SubscriptionInvoice::with([
            'company',
            'plan',
            'discount',
            'subscription',
            'vaPayment.logs' => fn($q) => $q->latest('received_at'),
        ])->findOrFail($id);

        $history = AuditLog::with('user:id,name')
            ->where('subject_type', SubscriptionInvoice::class)
            ->where('subject_id', $invoice->id)
            ->latest()
            ->get();

        return view('pages.superadmin.invoices.show', [
            'invoice'   => $invoice,
            'history'   => $history,
            'statuses'  => self::STATUSES,
            'banks'     => self::BANKS,
            'isOverdue' => $invoice->isPending() && $invoice->due_at && $invoice->due_at->isPast(),
            'canDelete' => $this->canDelete($invoice),
        ]);
    }

    /** Halaman cetak / simpan PDF dari browser */
    public function print($id)
    {
        $invoice = SubscriptionInvoice::with(['company', 'plan', 'discount', 'vaPayment'])->findOrFail($id);

        return view('pages.superadmin.invoices.print', [
            'invoice'  => $invoice,
            'statuses' => self::STATUSES,
        ]);
    }

    // =========================================================
    // EDIT + UPDATE — hanya catatan & jatuh tempo (invoice pending)
    // =========================================================
    public function edit($id)
    {
        $invoice = SubscriptionInvoice::with(['company', 'plan', 'vaPayment'])->findOrFail($id);

        return view('pages.superadmin.invoices.edit', compact('invoice'));
    }

    public function update(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::with('vaPayment')->findOrFail($id);

        $data = $request->validate([
            'notes'  => 'nullable|string|max:1000',
            'due_at' => 'nullable|date|after:now',
        ], [
            'due_at.after' => 'Jatuh tempo harus di masa depan.',
        ], ['due_at' => 'jatuh tempo', 'notes' => 'catatan']);

        $before = ['notes' => $invoice->notes, 'due_at' => optional($invoice->due_at)->toDateTimeString()];

        $invoice->notes = $data['notes'] ?? null;

        // Jatuh tempo hanya bisa diubah untuk invoice pending yang BELUM punya VA
        // (masa berlaku VA di Midtrans tidak ikut berubah)
        if (!empty($data['due_at']) && $invoice->isPending() && !$invoice->vaPayment) {
            $invoice->due_at = Carbon::parse($data['due_at']);
        }

        $invoice->save();

        AuditLog::record('update_invoice', $invoice, "Invoice {$invoice->invoice_number} diperbarui", [
            'before' => $before,
            'after'  => ['notes' => $invoice->notes, 'due_at' => optional($invoice->due_at)->toDateTimeString()],
        ]);

        return redirect()->route('superadmin.invoices.show', $invoice->id)->with('success', 'Invoice diperbarui.');
    }

    // =========================================================
    // VERIFIKASI LUNAS (manual)
    // =========================================================
    public function verify(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::with(['company', 'plan', 'vaPayment'])->findOrFail($id);

        $data = $request->validate([
            'method'    => 'required|in:transfer,cash,va,other',
            'reference' => 'nullable|string|max:100',
            'note'      => 'required|string|max:500',
        ], [], ['method' => 'metode', 'reference' => 'referensi', 'note' => 'catatan']);

        if (!in_array($invoice->status, ['pending', 'expired'], true)) {
            return back()->with('error', 'Hanya invoice Menunggu bayar / Kedaluwarsa yang bisa diverifikasi.');
        }

        $methodLabel = ['transfer' => 'Transfer manual', 'cash' => 'Tunai', 'va' => 'VA (webhook gagal)', 'other' => 'Lainnya'][$data['method']];

        DB::transaction(function () use ($invoice, $data, $methodLabel) {
            $invoice->notes = trim(($invoice->notes ? $invoice->notes . "\n" : '')
                . "[Verifikasi manual " . now()->format('d/m/Y H:i') . "] {$methodLabel}"
                . ($data['reference'] ? " · Ref: {$data['reference']}" : '')
                . " · {$data['note']}");
            $invoice->save();

            $invoice->markAsPaid();

            // FIX: status VA ikut lunas (sebelumnya tetap pending)
            if ($invoice->vaPayment && !$invoice->vaPayment->isPaid()) {
                $invoice->vaPayment->markAsPaid(null, $data['reference'] ?: null);
            }
        });

        // Bayar di luar VA → tutup VA di Midtrans supaya tidak dibayar dobel
        $vaWarning = null;
        if ($invoice->vaPayment && $data['method'] !== 'va') {
            $vaWarning = $this->expireAtMidtrans($invoice);
        }

        // Aktifkan langganan + notifikasi "Pembayaran berhasil" (sama dengan webhook)
        $subscription = $this->subscriptionService->activateFromInvoice($invoice->fresh());

        AuditLog::record('verify_invoice', $invoice, "Invoice {$invoice->invoice_number} diverifikasi lunas manual ({$methodLabel})", $data);

        $redirect = redirect()->route('superadmin.invoices.show', $invoice->id)
            ->with('success', "Invoice lunas. Langganan aktif sampai {$subscription->expires_at->format('d M Y')}.");

        return $vaWarning ? $redirect->with('warning', $vaWarning) : $redirect;
    }

    // =========================================================
    // TOLAK / BATALKAN
    // =========================================================
    public function reject(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::with('vaPayment')->findOrFail($id);

        $data = $request->validate(['notes' => 'required|string|max:500'], [], ['notes' => 'alasan']);

        if (!$invoice->isPending()) {
            return back()->with('error', 'Hanya invoice Menunggu bayar yang bisa dibatalkan.');
        }

        $vaWarning = $invoice->vaPayment ? $this->expireAtMidtrans($invoice) : null;

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'status' => 'cancelled',
                'notes'  => trim(($invoice->notes ? $invoice->notes . "\n" : '') . '[Dibatalkan ' . now()->format('d/m/Y H:i') . '] ' . $data['notes']),
            ]);

            // FIX: VA ikut dibatalkan (sebelumnya tetap pending & masih bisa dibayar)
            if ($invoice->vaPayment && $invoice->vaPayment->isPending()) {
                $invoice->vaPayment->markAsCancelled();
            }

            // Voucher yang terpakai dikembalikan kuotanya
            if ($invoice->discount_id) {
                SubscriptionDiscount::where('id', $invoice->discount_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        });

        AuditLog::record('reject_invoice', $invoice, "Invoice {$invoice->invoice_number} dibatalkan: {$data['notes']}");

        $redirect = redirect()->route('superadmin.invoices.show', $invoice->id)->with('success', 'Invoice dibatalkan.');

        return $vaWarning ? $redirect->with('warning', $vaWarning) : $redirect;
    }

    /** Tandai invoice pending yang lewat jatuh tempo sebagai kedaluwarsa */
    public function markExpired($id)
    {
        $invoice = SubscriptionInvoice::with('vaPayment')->findOrFail($id);

        if (!$invoice->isPending()) {
            return back()->with('error', 'Invoice ini tidak berstatus Menunggu bayar.');
        }

        $invoice->markAsExpired();
        $invoice->vaPayment?->isPending() && $invoice->vaPayment->markAsExpired();

        AuditLog::record('expire_invoice', $invoice, "Invoice {$invoice->invoice_number} ditandai kedaluwarsa");

        return back()->with('success', 'Invoice ditandai kedaluwarsa.');
    }

    // =========================================================
    // VA: buat / cek status di Midtrans
    // =========================================================
    public function createVa(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::with(['company', 'plan', 'vaPayment'])->findOrFail($id);

        $data = $request->validate(['bank' => 'required|in:' . implode(',', array_keys(self::BANKS))]);

        if (!$invoice->isPending()) {
            return back()->with('error', 'VA hanya bisa dibuat untuk invoice Menunggu bayar.');
        }

        if ($invoice->vaPayment && $invoice->vaPayment->isPending()) {
            return back()->with('error', 'Invoice ini sudah punya VA aktif.');
        }

        if ($invoice->due_at && $invoice->due_at->isPast()) {
            return back()->with('error', 'Invoice sudah lewat jatuh tempo. Ubah jatuh tempo atau buat invoice baru.');
        }

        try {
            $va = $this->vaPaymentService->createVa($invoice, $data['bank']);
        } catch (\Throwable $e) {
            Log::error('Gagal membuat VA dari panel', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);

            return back()->with('error', 'VA gagal dibuat: ' . $e->getMessage());
        }

        AuditLog::record('create_va', $invoice, "VA {$va->bank} {$va->va_number} dibuat untuk {$invoice->invoice_number}");

        return back()->with('success', 'VA ' . strtoupper($va->bank) . " berhasil dibuat: {$va->va_number}");
    }

    public function checkStatus($id)
    {
        $invoice = SubscriptionInvoice::with('vaPayment.invoice')->findOrFail($id);

        if (!$invoice->vaPayment) {
            return back()->with('error', 'Invoice ini belum punya VA.');
        }

        // Sudah lunas → jangan proses lagi (checkStatus akan mengaktifkan langganan dua kali)
        if ($invoice->isPaid() || $invoice->vaPayment->isPaid()) {
            return back()->with('success', 'Invoice sudah lunas, tidak perlu dicek ulang.');
        }

        try {
            $result = $this->vaPaymentService->checkStatus($invoice->vaPayment);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal cek ke Midtrans: ' . $e->getMessage());
        }

        $fresh = $invoice->fresh();

        return back()->with('success', 'Status di Midtrans: ' . ($result['transaction_status'] ?? '-')
            . '. Status invoice sekarang: ' . (self::STATUSES[$fresh->status] ?? $fresh->status) . '.');
    }

    // =========================================================
    // DESTROY — hanya invoice yang tidak pernah dibayar & tanpa VA
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::with('vaPayment')->findOrFail($id);

        if (!$this->canDelete($invoice)) {
            return back()->with('error', 'Invoice ini tidak bisa dihapus. Hanya invoice yang dibatalkan/kedaluwarsa dan tanpa VA yang boleh dihapus.');
        }

        $request->validate(['confirm' => 'required|in:' . $invoice->invoice_number], [
            'confirm.in' => 'Nomor invoice yang diketik tidak sama.',
        ]);

        $info = $invoice->only(['id', 'invoice_number', 'company_id', 'total_amount', 'status']);
        $invoice->delete();

        AuditLog::record('delete_invoice', null, "Invoice {$info['invoice_number']} dihapus", $info);

        return redirect()->route('superadmin.invoices.index')->with('success', "Invoice {$info['invoice_number']} dihapus.");
    }

    // =========================================================
    // HELPER
    // =========================================================

    private function filteredQuery(Request $request)
    {
        $query = SubscriptionInvoice::query();

        if ($request->filled('status')) {
            $request->status === 'overdue'
                ? $query->overdue()
                : $query->where('status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('from')) {
            $query->where('issued_at', '>=', Carbon::parse($request->from)->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('issued_at', '<=', Carbon::parse($request->to)->endOfDay());
        }

        // ?search= (dan ?invoice_number= / ?company= dari link lama)
        $search = trim((string) ($request->input('search') ?? $request->input('invoice_number') ?? $request->input('company') ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('invoice_number', 'like', $like)
                    ->orWhereHas('company', fn($c) => $c->where('name', 'like', $like));
            });
        }

        return $query;
    }

    private function canDelete(SubscriptionInvoice $invoice): bool
    {
        return in_array($invoice->status, ['cancelled', 'expired'], true) && !$invoice->vaPayment;
    }

    /**
     * Tutup transaksi VA di Midtrans supaya tidak bisa dibayar lagi.
     * @return string|null pesan peringatan kalau gagal
     */
    private function expireAtMidtrans(SubscriptionInvoice $invoice): ?string
    {
        if (!$invoice->vaPayment || $invoice->vaPayment->isPaid()) {
            return null;
        }

        try {
            new Midtrans(); // set server key
            Transaction::expire($invoice->invoice_number);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Gagal expire VA di Midtrans', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);

            return 'VA di Midtrans tidak berhasil ditutup otomatis (' . $e->getMessage() . '). Jika tenant terlanjur membayar ke VA ini, cek di dashboard Midtrans.';
        }
    }
}
