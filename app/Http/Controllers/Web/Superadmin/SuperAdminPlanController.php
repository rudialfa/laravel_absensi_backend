<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuperAdminPlanController extends Controller
{
    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $query = SubscriptionPlan::withCount([
            'subscriptions',
            'subscriptions as active_subscriptions_count' => fn($q) => $q->whereIn('status', ['trial', 'active', 'grace']),
        ]);

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $plans = $query->orderBy('sort_order')->orderBy('id')->get();

        // Pendapatan lunas per paket
        $revenue = SubscriptionInvoice::where('status', 'paid')
            ->select('plan_id', DB::raw('sum(total_amount) as total'), DB::raw('count(*) as paid_count'))
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        return view('pages.superadmin.plans.index', [
            'plans'    => $plans,
            'revenue'  => $revenue,
            'warnings' => $this->trialWarnings(),
        ]);
    }

    // =========================================================
    // CREATE + STORE
    // =========================================================
    public function create(Request $request)
    {
        // ?from=ID → salin data paket lain sebagai template
        $template = $request->filled('from') ? SubscriptionPlan::find($request->from) : null;

        return view('pages.superadmin.plans.create', [
            'template'     => $template,
            'nextSortOrder' => (int) SubscriptionPlan::max('sort_order') + 1,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $plan = new SubscriptionPlan();
        $plan->forceFill($data)->save();

        AuditLog::record('create_plan', $plan, "Paket {$plan->name} dibuat", ['data' => $data]);

        // FIX: sebelumnya ->route('pages.superadmin.plans.index') → error "Route not defined"
        return redirect()
            ->route('superadmin.plans.show', $plan->id)
            ->with('success', "Paket {$plan->name} berhasil dibuat.");
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $subsByStatus = CompanySubscription::where('plan_id', $plan->id)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $invoiceStats = SubscriptionInvoice::where('plan_id', $plan->id)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(total_amount) as amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $tenants = CompanySubscription::with('company:id,name,type,is_active')
            ->where('plan_id', $plan->id)
            ->orderByRaw("FIELD(status, 'active', 'trial', 'grace', 'expired', 'cancelled')")
            ->orderByDesc('expires_at')
            ->limit(15)
            ->get();

        $discounts = DB::table('subscription_discounts')
            ->where('plan_id', $plan->id)
            ->orderByDesc('is_active')
            ->get();

        return view('pages.superadmin.plans.show', [
            'plan'         => $plan,
            'subsByStatus' => $subsByStatus,
            'invoiceStats' => $invoiceStats,
            'tenants'      => $tenants,
            'discounts'    => $discounts,
            'canDelete'    => $this->usageCount($plan) === 0,
            'pricePerMonth' => $plan->duration_days > 0 ? (float) $plan->price / $plan->duration_days * 30 : 0,
        ]);
    }

    // =========================================================
    // EDIT + UPDATE
    // =========================================================
    public function edit($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return view('pages.superadmin.plans.edit', [
            'plan'     => $plan,
            'isInUse'  => $this->usageCount($plan) > 0,
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan   = SubscriptionPlan::findOrFail($id);
        $before = $plan->only(['name', 'slug', 'description', 'duration_days', 'price', 'is_free', 'is_active', 'is_popular', 'saving_label', 'sort_order']);
        $data   = $this->validated($request, $plan);

        $plan->forceFill($data)->save();

        AuditLog::record('update_plan', $plan, "Paket {$plan->name} diperbarui", [
            'before' => $before,
            'after'  => $plan->only(array_keys($before)),
        ]);

        return redirect()
            ->route('superadmin.plans.show', $plan->id)
            ->with('success', 'Paket langganan berhasil diperbarui.');
    }

    // =========================================================
    // AKTIF / NONAKTIF (tampil / sembunyi di aplikasi)
    // =========================================================
    public function toggleActive($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $plan->forceFill(['is_active' => !$plan->is_active])->save();

        AuditLog::record(
            $plan->is_active ? 'activate_plan' : 'deactivate_plan',
            $plan,
            "Paket {$plan->name} " . ($plan->is_active ? 'diaktifkan' : 'dinonaktifkan')
        );

        $message = $plan->is_active
            ? "Paket {$plan->name} aktif dan tampil di aplikasi."
            : "Paket {$plan->name} disembunyikan. Tenant yang sedang memakainya tidak terpengaruh.";

        return back()->with('success', $message);
    }

    // =========================================================
    // DESTROY — hanya kalau belum pernah dipakai sama sekali
    // =========================================================
    public function destroy($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        if ($this->usageCount($plan) > 0) {
            return back()->with(
                'error',
                "Paket {$plan->name} sudah pernah dipakai tenant/invoice, jadi tidak bisa dihapus (riwayat pembayaran harus tetap utuh). Nonaktifkan saja."
            );
        }

        $info = $plan->only(['id', 'name', 'slug', 'price', 'duration_days']);
        $plan->delete(); // voucher yang khusus paket ini → plan_id jadi null (berlaku semua paket)

        AuditLog::record('delete_plan', null, "Paket {$info['name']} dihapus", $info);

        return redirect()
            ->route('superadmin.plans.index')
            ->with('success', "Paket {$info['name']} berhasil dihapus.");
    }

    // =========================================================
    // HELPER
    // =========================================================

    /** Berapa kali paket dipakai (langganan + invoice, termasuk yang soft delete) */
    private function usageCount(SubscriptionPlan $plan): int
    {
        return DB::table('company_subscriptions')->where('plan_id', $plan->id)->count()
            + DB::table('subscription_invoices')->where('plan_id', $plan->id)->count();
    }

    /** Peringatan konfigurasi trial (SubscriptionService::startTrial pakai paket gratis aktif PERTAMA) */
    private function trialWarnings(): array
    {
        $freeActive = SubscriptionPlan::where('is_free', true)->where('is_active', true)->count();

        if ($freeActive === 0) {
            return ['Tidak ada paket gratis yang aktif. Trial untuk tenant baru akan GAGAL. Aktifkan atau buat paket trial (centang "Paket gratis").'];
        }

        if ($freeActive > 1) {
            return ["Ada {$freeActive} paket gratis yang aktif. Trial hanya memakai salah satunya (yang pertama ditemukan). Sebaiknya aktifkan satu saja."];
        }

        return [];
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        // Slug otomatis dari nama kalau kosong
        $request->merge([
            'slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name'))),
        ]);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('subscription_plans', 'slug')->ignore($plan?->id)],
            'description'   => 'nullable|string|max:2000',
            'duration_days' => 'required|integer|min:1|max:3650',
            'price'         => 'required|numeric|min:0|max:9999999999',
            'saving_label'  => 'nullable|string|max:50',
            'sort_order'    => 'nullable|integer|min:0|max:255',
        ], [], [
            'name'          => 'nama paket',
            'description'   => 'deskripsi',
            'duration_days' => 'durasi',
            'price'         => 'harga',
            'saving_label'  => 'label hemat',
            'sort_order'    => 'urutan',
        ]);

        $data['is_free']    = $request->boolean('is_free');
        $data['is_active']  = $request->boolean('is_active');
        $data['is_popular'] = $request->boolean('is_popular');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // Paket gratis selalu Rp 0; paket berbayar wajib punya harga (VA tidak bisa dibuat untuk Rp 0)
        if ($data['is_free']) {
            $data['price'] = 0;
        } elseif ((float) $data['price'] <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'price' => 'Harga paket berbayar harus lebih dari Rp 0. Centang "Paket gratis" kalau memang gratis.',
            ]);
        }

        return $data;
    }
}
