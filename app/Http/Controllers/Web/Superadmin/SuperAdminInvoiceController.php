<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SuperAdminInvoiceController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    public function index(Request $request)
    {
        $query = SubscriptionInvoice::with(['company', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('invoice_number')) {
            $query->where('invoice_number', 'like', '%' . $request->invoice_number . '%');
        }

        if ($request->filled('company')) {
            $query->whereHas('company', fn($q) => $q->where('name', 'like', '%' . $request->company . '%'));
        }

        $invoices = $query->latest('issued_at')->paginate(15)->withQueryString();

        return view('pages.superadmin.invoices.index', compact('invoices'));
    }

    public function show($id)
    {
        $invoice = SubscriptionInvoice::with(['company', 'plan', 'discount', 'vaPayment.logs'])
            ->findOrFail($id);

        return view('pages.superadmin.invoices.show', compact('invoice'));
    }

    /**
     * Verifikasi manual — dipakai kalau ada pembayaran yang tidak otomatis
     * ter-konfirmasi lewat webhook Midtrans (misal transfer manual / kendala VA).
     */
    public function verify(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::with(['company', 'plan'])->findOrFail($id);

        if (!$invoice->isPending()) {
            return redirect()
                ->route('superadmin.invoices.show', $invoice->id)
                ->with('error', 'Invoice ini sudah tidak berstatus pending.');
        }

        $invoice->markAsPaid();
        $this->subscriptionService->activateFromInvoice($invoice->fresh());

        AuditLog::record('verify_invoice', $invoice, "Invoice {$invoice->invoice_number} diverifikasi manual oleh superadmin");

        return redirect()
            ->route('superadmin.invoices.show', $invoice->id)
            ->with('success', 'Invoice berhasil diverifikasi dan langganan diaktifkan.');
    }

    public function reject(Request $request, $id)
    {
        $invoice = SubscriptionInvoice::findOrFail($id);

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        if (!$invoice->isPending()) {
            return redirect()
                ->route('superadmin.invoices.show', $invoice->id)
                ->with('error', 'Invoice ini sudah tidak berstatus pending.');
        }

        $invoice->update([
            'status' => 'cancelled',
            'notes'  => $request->notes ?? $invoice->notes,
        ]);

        AuditLog::record('reject_invoice', $invoice, "Invoice {$invoice->invoice_number} ditolak oleh superadmin");

        return redirect()
            ->route('superadmin.invoices.show', $invoice->id)
            ->with('success', 'Invoice berhasil ditolak/dibatalkan.');
    }
}
