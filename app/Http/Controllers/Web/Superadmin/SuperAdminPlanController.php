<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SuperAdminPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::withCount('subscriptions')
            ->orderBy('sort_order')
            ->paginate(15);

        return view('pages.superadmin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('pages.superadmin.plans.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['is_free']   = $request->boolean('is_free');
        $data['is_active'] = $request->boolean('is_active', true);

        $plan = SubscriptionPlan::create($data);

        AuditLog::record('create_plan', $plan, "Paket {$plan->name} dibuat");

        return redirect()
            ->route('pages.superadmin.plans.index')
            ->with('success', 'Paket langganan berhasil dibuat.');
    }

    public function edit($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return view('pages.superadmin.plans.edit', compact('plan'));
    }

    public function update(Request $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $data = $this->validated($request, $plan->id);
        $data['is_free']   = $request->boolean('is_free');
        $data['is_active'] = $request->boolean('is_active');

        $plan->update($data);

        AuditLog::record('update_plan', $plan, "Paket {$plan->name} diperbarui");

        return redirect()
            ->route('superadmin.plans.index')
            ->with('success', 'Paket langganan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        if ($plan->subscriptions()->exists()) {
            return redirect()
                ->route('superadmin.plans.index')
                ->with('error', 'Paket tidak bisa dihapus karena masih dipakai oleh tenant.');
        }

        $name = $plan->name;
        $plan->delete();

        AuditLog::record('delete_plan', null, "Paket {$name} (ID: {$id}) dihapus");

        return redirect()
            ->route('superadmin.plans.index')
            ->with('success', 'Paket langganan berhasil dihapus.');
    }

    private function validated(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'name'          => 'required|string|max:255',
            'slug'          => 'required|string|max:255|unique:subscription_plans,slug,' . $ignoreId,
            'description'   => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'price'         => 'required|numeric|min:0',
            'is_free'       => 'nullable|boolean',
            'is_active'     => 'nullable|boolean',
            'sort_order'    => 'nullable|integer|min:0',
        ]);
    }
}
