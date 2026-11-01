<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionDiscount;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SuperAdminDiscountController extends Controller
{
    /**
     * List semua voucher/diskon.
     */
    public function index(Request $request)
    {
        $query = SubscriptionDiscount::with('plan')->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $discounts = $query->paginate(15)->withQueryString();

        return view('pages.superadmin.discounts.index', compact('discounts'));
    }

    public function create()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        return view('pages.superadmin.discounts.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $discount = SubscriptionDiscount::create($data);

        $this->log('create_discount', $discount, "Membuat voucher {$discount->name} ({$discount->code})");

        return redirect()
            ->route('superadmin.discounts.index')
            ->with('success', 'Voucher berhasil dibuat.');
    }

    public function edit(string $id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);
        $plans = SubscriptionPlan::orderBy('sort_order')->get();

        return view('pages.superadmin.discounts.edit', compact('discount', 'plans'));
    }

    public function update(Request $request, string $id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);
        $data = $this->validated($request, $discount->id);

        $discount->update($data);

        $this->log('update_discount', $discount, "Mengubah voucher {$discount->name} ({$discount->code})");

        return redirect()
            ->route('superadmin.discounts.index')
            ->with('success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(string $id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);

        if ($discount->used_count > 0) {
            return back()->with('error', 'Voucher sudah pernah dipakai, tidak bisa dihapus. Nonaktifkan saja.');
        }

        $name = $discount->name;
        $discount->delete();

        $this->log('delete_discount', null, "Menghapus voucher {$name}", ['discount_id' => $id]);

        return redirect()
            ->route('superadmin.discounts.index')
            ->with('success', 'Voucher berhasil dihapus.');
    }

    /**
     * Toggle aktif / nonaktif tanpa harus buka form edit.
     */
    public function toggleActive(string $id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);
        $discount->is_active = ! $discount->is_active;
        $discount->save();

        $status = $discount->is_active ? 'diaktifkan' : 'dinonaktifkan';
        $this->log('toggle_discount', $discount, "Voucher {$discount->code} {$status}");

        return back()->with('success', "Voucher berhasil {$status}.");
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:subscription_discounts,code' . ($ignoreId ? ",{$ignoreId}" : ''),
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'max_usage' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $data = $validator->validate();
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function log(string $action, mixed $subject, string $description, array $meta = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject->id ?? null,
            'description' => $description,
            'meta' => $meta ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
