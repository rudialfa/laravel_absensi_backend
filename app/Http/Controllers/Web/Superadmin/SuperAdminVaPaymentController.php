<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\VaPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminVaPaymentController extends Controller
{
    /**
     * List semua VA payment lintas tenant, buat monitoring pembayaran.
     */
    public function index(Request $request)
    {
        $query = VaPayment::with(['company', 'invoice'])->latest();

        if ($request->filled('bank')) {
            $query->where('bank', $request->bank);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('va_number', 'like', "%{$search}%")
                    ->orWhereHas('company', fn($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $vaPayments = $query->paginate(20)->withQueryString();

        return view('pages.superadmin.va-payments.index', compact('vaPayments'));
    }

    /**
     * Detail 1 VA payment + timeline webhook log (inquiry/payment/inquiry_status).
     */
    public function show(string $id)
    {
        $vaPayment = VaPayment::with(['company', 'invoice.plan'])->findOrFail($id);

        $logs = $vaPayment->logs()->orderBy('received_at', 'desc')->get();

        return view('pages.superadmin.va-payments.show', compact('vaPayment', 'logs'));
    }

    /**
     * Override manual: tandai VA (dan invoice terkait) lunas.
     * Dipakai kalau webhook bank gagal masuk tapi customer sudah transfer (butuh bukti manual).
     */
    public function markPaid(Request $request, string $id)
    {
        $vaPayment = VaPayment::with('invoice.subscription.plan')->findOrFail($id);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        if ($vaPayment->status === 'paid') {
            return back()->with('error', 'VA ini sudah berstatus lunas.');
        }

        DB::transaction(function () use ($vaPayment) {
            $vaPayment->status = 'paid';
            $vaPayment->paid_at = now();
            $vaPayment->save();

            $invoice = $vaPayment->invoice;
            if ($invoice && $invoice->status !== 'paid') {
                $invoice->status = 'paid';
                $invoice->paid_at = now();
                $invoice->save();
            }

            $subscription = $invoice?->subscription;
            if ($subscription) {
                $plan = $subscription->plan;
                $base = $subscription->expires_at && \Carbon\Carbon::parse($subscription->expires_at)->isFuture()
                    ? \Carbon\Carbon::parse($subscription->expires_at)
                    : now();

                $subscription->status = 'active';
                $subscription->expires_at = $base->addDays($plan->duration_days ?? 30);
                $subscription->last_invoice_id = $invoice->id;
                $subscription->save();
            }
        });

        AuditLog::record(
            'manual_mark_va_paid',
            $vaPayment,
            "Menandai VA {$vaPayment->va_number} ({$vaPayment->bank}) lunas secara manual. Alasan: {$data['reason']}"
        );

        return back()->with('success', 'VA & invoice berhasil ditandai lunas, langganan tenant diperpanjang otomatis.');
    }
}
