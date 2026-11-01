<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminCompanySubscriptionController extends Controller
{
    /**
     * List semua langganan tenant (semua company).
     */
    public function index(Request $request)
    {
        $query = CompanySubscription::with(['company', 'plan'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('company', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->paginate(15)->withQueryString();
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return view('pages.superadmin.subscriptions.index', compact('subscriptions', 'plans'));
    }

    /**
     * Detail 1 langganan tenant: histori invoice + VA payment.
     */
    public function show(string $id)
    {
        $subscription = CompanySubscription::with(['company', 'plan'])->findOrFail($id);

        $invoices = $subscription->invoices()
            ->with(['plan', 'discount', 'vaPayment'])
            ->latest()
            ->paginate(10);

        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();

        return view('pages.superadmin.subscriptions.show', compact('subscription', 'invoices', 'plans'));
    }

    /**
     * Perpanjang masa aktif secara manual (misal komplain, kompensasi gangguan, dsb).
     */
    public function extend(Request $request, string $id)
    {
        $subscription = CompanySubscription::findOrFail($id);

        $data = $request->validate([
            'extend_days' => 'required|integer|min:1|max:730',
            'reason' => 'required|string|max:500',
        ]);

        $before = $subscription->expires_at;

        // Perpanjangan dihitung dari expires_at kalau masih di masa depan,
        // atau dari sekarang kalau sudah kadaluarsa.
        $base = $subscription->expires_at && Carbon::parse($subscription->expires_at)->isFuture()
            ? Carbon::parse($subscription->expires_at)
            : now();

        $subscription->expires_at = $base->addDays($data['extend_days']);

        if (in_array($subscription->status, ['expired', 'grace', 'cancelled'])) {
            $subscription->status = 'active';
        }

        $subscription->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'extend_subscription',
            'subject_type' => CompanySubscription::class,
            'subject_id' => $subscription->id,
            'description' => "Memperpanjang langganan tenant #{$subscription->company_id} sebanyak {$data['extend_days']} hari. Alasan: {$data['reason']}",
            'meta' => [
                'before_expires_at' => $before,
                'after_expires_at' => $subscription->expires_at,
                'extend_days' => $data['extend_days'],
                'reason' => $data['reason'],
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Masa langganan berhasil diperpanjang.');
    }

    /**
     * Ganti paket langganan tenant secara manual (bukan lewat pembayaran).
     */
    public function changePlan(Request $request, string $id)
    {
        $subscription = CompanySubscription::findOrFail($id);

        $data = $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'reset_period' => 'boolean',
            'reason' => 'required|string|max:500',
        ]);

        $newPlan = SubscriptionPlan::findOrFail($data['plan_id']);
        $oldPlanId = $subscription->plan_id;

        DB::transaction(function () use ($subscription, $newPlan, $request, $data) {
            $subscription->plan_id = $newPlan->id;

            if ($request->boolean('reset_period')) {
                $subscription->started_at = now();
                $subscription->expires_at = now()->addDays($newPlan->duration_days);
                $subscription->status = 'active';
            }

            $subscription->save();
        });

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'change_subscription_plan',
            'subject_type' => CompanySubscription::class,
            'subject_id' => $subscription->id,
            'description' => "Mengubah paket tenant #{$subscription->company_id} dari plan #{$oldPlanId} ke {$newPlan->name}. Alasan: {$data['reason']}",
            'meta' => [
                'old_plan_id' => $oldPlanId,
                'new_plan_id' => $newPlan->id,
                'reset_period' => $request->boolean('reset_period'),
                'reason' => $data['reason'],
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Paket langganan berhasil diubah.');
    }

    /**
     * Batalkan langganan secara manual.
     */
    public function cancel(Request $request, string $id)
    {
        $subscription = CompanySubscription::findOrFail($id);

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $subscription->status = 'cancelled';
        $subscription->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'cancel_subscription',
            'subject_type' => CompanySubscription::class,
            'subject_id' => $subscription->id,
            'description' => "Membatalkan langganan tenant #{$subscription->company_id}. Alasan: {$data['reason']}",
            'meta' => ['reason' => $data['reason']],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Langganan berhasil dibatalkan.');
    }

    /**
     * Aktifkan kembali langganan yang tadinya expired/cancelled (tanpa ubah tanggal).
     */
    public function reactivate(Request $request, string $id)
    {
        $subscription = CompanySubscription::findOrFail($id);

        if ($subscription->expires_at && Carbon::parse($subscription->expires_at)->isPast()) {
            return back()->with('error', 'Tidak bisa mengaktifkan, masa aktif sudah lewat. Gunakan "Perpanjang" dulu.');
        }

        $subscription->status = 'active';
        $subscription->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'reactivate_subscription',
            'subject_type' => CompanySubscription::class,
            'subject_id' => $subscription->id,
            'description' => "Mengaktifkan kembali langganan tenant #{$subscription->company_id}.",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Langganan berhasil diaktifkan kembali.');
    }
}
