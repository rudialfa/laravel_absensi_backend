<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Langganan tenant (1 tenant = 1 baris company_subscriptions).
 * Semua perubahan manual WAJIB disertai alasan & tercatat di Audit Log.
 */
class SuperAdminCompanySubscriptionController extends Controller
{
    public const STATUSES = [
        'trial'     => 'Trial',
        'active'    => 'Aktif',
        'grace'     => 'Masa toleransi',
        'expired'   => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
    ];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        // Rapikan status yang sudah lewat masa aktif (kalau scheduler belum jalan)
        $this->syncOverdue();

        $query = CompanySubscription::with(['company:id,name,type,is_active', 'plan:id,name,is_free']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('type')) {
            $query->whereHas('company', fn($q) => $q->where('type', $request->type));
        }

        if ($request->filled('expiring')) {
            $days = (int) $request->expiring;
            $query->whereIn('status', ['trial', 'active'])
                ->whereBetween('expires_at', [now(), now()->addDays($days)]);
        }

        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->whereHas('company', fn($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        match ($request->input('sort', 'expires')) {
            'newest'  => $query->latest(),
            'expires_desc' => $query->orderByDesc('expires_at'),
            default   => $query->orderBy('expires_at'),
        };

        $subscriptions = $query->paginate(20)->withQueryString();

        $counts = CompanySubscription::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('pages.superadmin.subscriptions.index', [
            'subscriptions'  => $subscriptions,
            'plans'          => SubscriptionPlan::orderBy('sort_order')->get(['id', 'name']),
            'counts'         => $counts,
            'statuses'       => self::STATUSES,
            'typeLabels'     => SuperAdminTenantController::SUPPORTED_TYPES,
            'withoutSubCount' => Company::doesntHave('subscriptions')->count(),
        ]);
    }

    // =========================================================
    // CREATE + STORE — buat langganan untuk tenant yang belum punya
    // =========================================================
    public function create(Request $request)
    {
        $companies = Company::doesntHave('subscriptions')->orderBy('name')->get(['id', 'name', 'type']);

        if ($companies->isEmpty()) {
            return redirect()
                ->route('superadmin.subscriptions.index')
                ->with('warning', 'Semua tenant sudah punya langganan. Untuk mengubah, buka detail langganannya.');
        }

        return view('pages.superadmin.subscriptions.create', [
            'companies'  => $companies,
            'plans'      => SubscriptionPlan::orderBy('sort_order')->get(),
            'presetId'   => $request->input('company_id'),
            'typeLabels' => SuperAdminTenantController::SUPPORTED_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_id'    => ['required', 'exists:companies,id', Rule::unique('company_subscriptions', 'company_id')],
            'plan_id'       => 'required|exists:subscription_plans,id',
            'started_at'    => 'required|date',
            'duration_days' => 'required|integer|min:1|max:3650',
            'reason'        => 'required|string|max:500',
        ], [
            'company_id.unique' => 'Tenant ini sudah punya langganan.',
        ], [
            'company_id'    => 'tenant',
            'plan_id'       => 'paket',
            'started_at'    => 'tanggal mulai',
            'duration_days' => 'durasi',
            'reason'        => 'alasan',
        ]);

        $plan    = SubscriptionPlan::findOrFail($data['plan_id']);
        $start   = Carbon::parse($data['started_at'])->startOfDay();
        $expires = $start->copy()->addDays((int) $data['duration_days']);

        $subscription = new CompanySubscription();
        $subscription->forceFill([
            'company_id'     => $data['company_id'],
            'plan_id'        => $plan->id,
            'status'         => $plan->is_free ? 'trial' : 'active',
            'started_at'     => $start,
            'expires_at'     => $expires,
            'has_used_trial' => (bool) $plan->is_free,
        ])->save();

        $subscription->syncStatus();

        $this->log('create_subscription', $subscription, "Langganan {$plan->name} dibuat manual untuk tenant #{$subscription->company_id}", $data['reason'], [
            'started_at' => $start->toDateTimeString(),
            'expires_at' => $expires->toDateTimeString(),
        ]);

        return redirect()
            ->route('superadmin.subscriptions.show', $subscription->id)
            ->with('success', "Langganan dibuat, aktif sampai {$expires->format('d M Y')}.");
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $subscription = CompanySubscription::with(['company', 'plan', 'lastInvoice'])->findOrFail($id);
        $subscription->syncStatus();

        $invoices = $subscription->invoices()
            ->with(['plan', 'discount', 'vaPayment'])
            ->latest('issued_at')
            ->paginate(10);

        $history = AuditLog::with('user:id,name')
            ->where('subject_type', CompanySubscription::class)
            ->where('subject_id', $subscription->id)
            ->latest()
            ->limit(15)
            ->get();

        $paidTotal = $subscription->invoices()->where('status', 'paid')->sum('total_amount');

        return view('pages.superadmin.subscriptions.show', [
            'subscription' => $subscription,
            'invoices'     => $invoices,
            'history'      => $history,
            'paidTotal'    => $paidTotal,
            'plans'        => SubscriptionPlan::orderBy('sort_order')->get(),
            'statuses'     => self::STATUSES,
            'graceDays'    => CompanySubscription::graceDays(),
            'canDelete'    => !$subscription->invoices()->exists(),
            'typeLabels'   => SuperAdminTenantController::SUPPORTED_TYPES,
        ]);
    }

    // =========================================================
    // EDIT + UPDATE — atur tanggal & status secara langsung
    // =========================================================
    public function edit($id)
    {
        $subscription = CompanySubscription::with(['company', 'plan'])->findOrFail($id);

        return view('pages.superadmin.subscriptions.edit', [
            'subscription' => $subscription,
            'plans'        => SubscriptionPlan::orderBy('sort_order')->get(),
            'statuses'     => self::STATUSES,
        ]);
    }

    public function update(Request $request, $id)
    {
        $subscription = CompanySubscription::findOrFail($id);

        $data = $request->validate([
            'plan_id'        => 'required|exists:subscription_plans,id',
            'status'         => ['required', Rule::in(array_keys(self::STATUSES))],
            'started_at'     => 'required|date',
            'expires_at'     => 'required|date|after:started_at',
            'has_used_trial' => 'nullable|boolean',
            'reason'         => 'required|string|max:500',
        ], [], [
            'plan_id'    => 'paket',
            'started_at' => 'tanggal mulai',
            'expires_at' => 'tanggal berakhir',
            'reason'     => 'alasan',
        ]);

        $before = $this->snapshot($subscription);

        $subscription->forceFill([
            'plan_id'        => $data['plan_id'],
            'status'         => $data['status'],
            'started_at'     => Carbon::parse($data['started_at']),
            'expires_at'     => Carbon::parse($data['expires_at']),
            'has_used_trial' => $request->boolean('has_used_trial'),
        ])->save();

        // Status trial/active dengan tanggal yang sudah lewat otomatis jadi grace/expired
        $subscription->syncStatus();

        $this->log('update_subscription', $subscription, "Langganan tenant #{$subscription->company_id} diubah manual", $data['reason'], [
            'before' => $before,
            'after'  => $this->snapshot($subscription->fresh()),
        ]);

        return redirect()
            ->route('superadmin.subscriptions.show', $subscription->id)
            ->with('success', 'Langganan berhasil diperbarui. Status sekarang: ' . (self::STATUSES[$subscription->status] ?? $subscription->status) . '.');
    }

    // =========================================================
    // AKSI CEPAT
    // =========================================================

    /** Perpanjang N hari (dari tanggal berakhir kalau masih aktif, dari hari ini kalau sudah lewat) */
    public function extend(Request $request, $id)
    {
        $subscription = CompanySubscription::with('plan')->findOrFail($id);

        $data = $request->validate([
            'extend_days' => 'required|integer|min:1|max:730',
            'reason'      => 'required|string|max:500',
        ], [], ['extend_days' => 'jumlah hari', 'reason' => 'alasan']);

        $before = $this->snapshot($subscription);

        $base = $subscription->expires_at && $subscription->expires_at->isFuture()
            ? $subscription->expires_at->copy()
            : now();

        $subscription->expires_at = $base->addDays((int) $data['extend_days']);

        // Yang sudah terkunci dibuka lagi; trial tetap trial kalau paketnya gratis
        if (in_array($subscription->status, ['expired', 'grace', 'cancelled'], true)) {
            $subscription->status = ($subscription->plan && $subscription->plan->is_free) ? 'trial' : 'active';
        }

        $subscription->save();

        $this->log('extend_subscription', $subscription, "Langganan tenant #{$subscription->company_id} diperpanjang {$data['extend_days']} hari", $data['reason'], [
            'before' => $before,
            'after'  => $this->snapshot($subscription),
        ]);

        return back()->with('success', "Diperpanjang {$data['extend_days']} hari, aktif sampai {$subscription->expires_at->format('d M Y H:i')}.");
    }

    /** Ganti paket tanpa pembayaran */
    public function changePlan(Request $request, $id)
    {
        $subscription = CompanySubscription::with('plan')->findOrFail($id);

        $data = $request->validate([
            'plan_id'      => 'required|exists:subscription_plans,id',
            'reset_period' => 'nullable|boolean',
            'reason'       => 'required|string|max:500',
        ], [], ['plan_id' => 'paket', 'reason' => 'alasan']);

        $newPlan = SubscriptionPlan::findOrFail($data['plan_id']);

        if ((int) $newPlan->id === (int) $subscription->plan_id && !$request->boolean('reset_period')) {
            return back()->with('warning', 'Paket yang dipilih sama dengan paket sekarang.');
        }

        $before = $this->snapshot($subscription);

        $subscription->plan_id = $newPlan->id;

        if ($request->boolean('reset_period')) {
            $subscription->started_at = now();
            $subscription->expires_at = now()->addDays($newPlan->duration_days);
            $subscription->status     = $newPlan->is_free ? 'trial' : 'active';
        } elseif ($subscription->status === 'trial' && !$newPlan->is_free) {
            // Dari trial ke paket berbayar → status jadi aktif
            $subscription->status = 'active';
        }

        if ($newPlan->is_free) {
            $subscription->has_used_trial = true;
        }

        $subscription->save();
        $subscription->syncStatus();

        $this->log('change_subscription_plan', $subscription, "Paket tenant #{$subscription->company_id} diganti ke {$newPlan->name}", $data['reason'], [
            'before'       => $before,
            'after'        => $this->snapshot($subscription->fresh()),
            'reset_period' => $request->boolean('reset_period'),
        ]);

        return back()->with('success', "Paket diganti ke {$newPlan->name}.");
    }

    /** Batalkan (akses langsung terkunci) */
    public function cancel(Request $request, $id)
    {
        $subscription = CompanySubscription::findOrFail($id);

        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        if ($subscription->status === 'cancelled') {
            return back()->with('warning', 'Langganan ini sudah dibatalkan.');
        }

        $before = $this->snapshot($subscription);
        $subscription->forceFill(['status' => 'cancelled'])->save();

        $this->log('cancel_subscription', $subscription, "Langganan tenant #{$subscription->company_id} dibatalkan", $data['reason'], [
            'before' => $before,
        ]);

        return back()->with('success', 'Langganan dibatalkan. Seluruh pengguna tenant terkunci sampai langganan dibuka lagi.');
    }

    /** Buka lagi yang dibatalkan/kedaluwarsa tanpa mengubah tanggal (hanya kalau tanggal masih berlaku) */
    public function reactivate(Request $request, $id)
    {
        $subscription = CompanySubscription::with('plan')->findOrFail($id);

        if ($subscription->expires_at && $subscription->expires_at->isPast()) {
            return back()->with('error', 'Masa aktif sudah lewat. Gunakan "Perpanjang" agar tanggal berakhir ikut diperbarui.');
        }

        $before = $this->snapshot($subscription);

        $subscription->forceFill([
            'status' => ($subscription->plan && $subscription->plan->is_free) ? 'trial' : 'active',
        ])->save();

        $this->log('reactivate_subscription', $subscription, "Langganan tenant #{$subscription->company_id} diaktifkan kembali", $request->input('reason'), [
            'before' => $before,
        ]);

        return back()->with('success', 'Langganan diaktifkan kembali.');
    }

    /** Sinkronkan status 1 langganan sesuai tanggal (sama seperti scheduler) */
    public function sync($id)
    {
        $subscription = CompanySubscription::findOrFail($id);
        $before = $subscription->status;

        $subscription->syncStatus();

        return back()->with('success', $before === $subscription->status
            ? 'Status sudah sesuai: ' . (self::STATUSES[$subscription->status] ?? $subscription->status) . '.'
            : 'Status diperbarui dari ' . (self::STATUSES[$before] ?? $before) . ' ke ' . (self::STATUSES[$subscription->status] ?? $subscription->status) . '.');
    }

    // =========================================================
    // DESTROY — hanya jika belum punya invoice
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $subscription = CompanySubscription::with('company')->findOrFail($id);

        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        // FK invoice → subscription = cascade: menghapus langganan akan menghapus riwayat invoice
        if ($subscription->invoices()->exists()) {
            return back()->with('error', 'Langganan ini punya riwayat invoice, jadi tidak boleh dihapus (riwayat pembayaran ikut terhapus). Gunakan "Batalkan".');
        }

        $snapshot  = $this->snapshot($subscription);
        $companyId = $subscription->company_id;

        $subscription->delete();

        AuditLog::record('delete_subscription', null, "Langganan tenant #{$companyId} dihapus. Alasan: {$data['reason']}", array_merge($snapshot, [
            'company_id' => $companyId,
            'reason'     => $data['reason'],
        ]));

        return redirect()
            ->route('superadmin.tenants.show', $companyId)
            ->with('success', 'Langganan dihapus. Tenant sekarang berstatus belum berlangganan (bisa mulai trial lagi dari aplikasi bila belum pernah trial).');
    }

    // =========================================================
    // HELPER
    // =========================================================

    private function snapshot(CompanySubscription $s): array
    {
        return [
            'plan_id'        => $s->plan_id,
            'status'         => $s->status,
            'started_at'     => optional($s->started_at)->toDateTimeString(),
            'expires_at'     => optional($s->expires_at)->toDateTimeString(),
            'has_used_trial' => (bool) $s->has_used_trial,
        ];
    }

    private function log(string $action, CompanySubscription $subject, string $description, ?string $reason, array $meta = []): void
    {
        AuditLog::record(
            $action,
            $subject,
            $reason ? "{$description}. Alasan: {$reason}" : $description,
            array_merge($meta, ['reason' => $reason])
        );
    }

    /** Status trial/active/grace yang tanggalnya sudah lewat → disinkronkan */
    private function syncOverdue(): void
    {
        CompanySubscription::whereIn('status', ['trial', 'active', 'grace'])
            ->where('expires_at', '<=', now())
            ->limit(200)
            ->get()
            ->each->syncStatus();
    }
}