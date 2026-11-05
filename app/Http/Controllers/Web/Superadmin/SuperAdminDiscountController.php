<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionDiscount;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SuperAdminDiscountController extends Controller
{
    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $query = SubscriptionDiscount::with('plan:id,name');
        $now   = now();

        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->where(fn($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        // Status "nyata" (bukan cuma is_active): berlaku / belum mulai / kedaluwarsa / kuota habis / nonaktif
        match ($request->input('status')) {
            'running' => $query->where('is_active', true)
                ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
                ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now))
                ->where(fn($q) => $q->whereNull('max_usage')->orWhereColumn('used_count', '<', 'max_usage')),
            'scheduled' => $query->where('is_active', true)->where('valid_from', '>', $now),
            'expired'   => $query->whereNotNull('valid_until')->where('valid_until', '<', $now),
            'exhausted' => $query->whereNotNull('max_usage')->whereColumn('used_count', '>=', 'max_usage'),
            'inactive'  => $query->where('is_active', false),
            default     => null,
        };

        if ($request->filled('kind')) {
            $request->kind === 'auto' ? $query->whereNull('code') : $query->whereNotNull('code');
        }

        if ($request->filled('plan_id')) {
            $request->plan_id === 'all'
                ? $query->whereNull('plan_id')
                : $query->where('plan_id', $request->plan_id);
        }

        $discounts = $query->latest()->paginate(15)->withQueryString();

        return view('pages.superadmin.discounts.index', [
            'discounts' => $discounts,
            'plans'     => SubscriptionPlan::orderBy('sort_order')->get(['id', 'name']),
            'warnings'  => $this->autoDiscountWarnings(),
        ]);
    }

    // =========================================================
    // CREATE + STORE
    // =========================================================
    public function create(Request $request)
    {
        $template = $request->filled('from') ? SubscriptionDiscount::find($request->from) : null;

        return view('pages.superadmin.discounts.create', [
            'plans'    => SubscriptionPlan::orderBy('sort_order')->get(),
            'template' => $template,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $discount = new SubscriptionDiscount();
        $discount->forceFill(array_merge($data, ['used_count' => 0]))->save();

        AuditLog::record('create_discount', $discount, "Voucher {$discount->name}" . ($discount->code ? " ({$discount->code})" : ' (otomatis)') . ' dibuat', ['data' => $data]);

        return redirect()
            ->route('superadmin.discounts.show', $discount->id)
            ->with('success', 'Voucher berhasil dibuat.');
    }

    // =========================================================
    // SHOW — detail + riwayat pemakaian
    // =========================================================
    public function show($id)
    {
        $discount = SubscriptionDiscount::with('plan')->findOrFail($id);

        $usage = SubscriptionInvoice::with(['company:id,name', 'plan:id,name'])
            ->where('discount_id', $discount->id)
            ->latest('issued_at')
            ->paginate(15);

        $stats = SubscriptionInvoice::where('discount_id', $discount->id)
            ->select('status', DB::raw('count(*) as total'), DB::raw('sum(discount_amount) as discount_sum'), DB::raw('sum(total_amount) as amount_sum'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('pages.superadmin.discounts.show', [
            'discount' => $discount,
            'usage'    => $usage,
            'stats'    => $stats,
            'state'    => self::state($discount),
            'canDelete' => $this->usageCount($discount) === 0,
        ]);
    }

    // =========================================================
    // EDIT + UPDATE
    // =========================================================
    public function edit($id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);

        return view('pages.superadmin.discounts.edit', [
            'discount' => $discount,
            'plans'    => SubscriptionPlan::orderBy('sort_order')->get(),
            'inUse'    => $this->usageCount($discount) > 0,
        ]);
    }

    public function update(Request $request, $id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);
        $before   = $discount->only(['name', 'code', 'discount_type', 'discount_value', 'max_discount_amount', 'plan_id', 'valid_from', 'valid_until', 'max_usage', 'is_active']);
        $data     = $this->validated($request, $discount);

        if (!empty($data['max_usage']) && $data['max_usage'] < $discount->used_count) {
            throw ValidationException::withMessages([
                'max_usage' => "Voucher sudah dipakai {$discount->used_count} kali, batas pemakaian tidak boleh lebih kecil dari itu.",
            ]);
        }

        $discount->forceFill($data)->save();

        AuditLog::record('update_discount', $discount, "Voucher {$discount->name} diperbarui", [
            'before' => $before,
            'after'  => $discount->only(array_keys($before)),
        ]);

        return redirect()
            ->route('superadmin.discounts.show', $discount->id)
            ->with('success', 'Voucher berhasil diperbarui.');
    }

    // =========================================================
    // AKTIF / NONAKTIF
    // =========================================================
    public function toggleActive($id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);
        $discount->forceFill(['is_active' => !$discount->is_active])->save();

        $status = $discount->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::record('toggle_discount', $discount, "Voucher {$discount->name} {$status}");

        return back()->with('success', "Voucher {$discount->name} {$status}.");
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy($id)
    {
        $discount = SubscriptionDiscount::findOrFail($id);

        if ($this->usageCount($discount) > 0) {
            return back()->with('error', 'Voucher sudah pernah dipakai di invoice, jadi tidak bisa dihapus (riwayat diskon harus tetap utuh). Nonaktifkan saja.');
        }

        $info = $discount->only(['id', 'name', 'code']);
        $discount->delete();

        AuditLog::record('delete_discount', null, "Voucher {$info['name']} dihapus", $info);

        return redirect()
            ->route('superadmin.discounts.index')
            ->with('success', 'Voucher berhasil dihapus.');
    }

    // =========================================================
    // HELPER
    // =========================================================

    /**
     * Status nyata voucher untuk ditampilkan.
     * @return array{key:string,label:string,badge:string}
     */
    public static function state($d): array
    {
        $now   = now();
        $from  = $d->valid_from ? Carbon::parse($d->valid_from) : null;
        $until = $d->valid_until ? Carbon::parse($d->valid_until) : null;

        return match (true) {
            !$d->is_active => ['key' => 'inactive', 'label' => 'Nonaktif', 'badge' => 'secondary'],
            $until && $until->lt($now) => ['key' => 'expired', 'label' => 'Kedaluwarsa', 'badge' => 'dark'],
            $d->max_usage && $d->used_count >= $d->max_usage => ['key' => 'exhausted', 'label' => 'Kuota habis', 'badge' => 'warning'],
            $from && $from->gt($now) => ['key' => 'scheduled', 'label' => 'Belum mulai', 'badge' => 'info'],
            default => ['key' => 'running', 'label' => 'Berlaku', 'badge' => 'success'],
        };
    }

    private function usageCount(SubscriptionDiscount $discount): int
    {
        return max(
            (int) $discount->used_count,
            SubscriptionInvoice::where('discount_id', $discount->id)->count()
        );
    }

    /** Diskon otomatis (tanpa kode) yang aktif bersamaan bisa membingungkan */
    private function autoDiscountWarnings(): array
    {
        $now = now();

        $autoRunning = SubscriptionDiscount::whereNull('code')
            ->where('is_active', true)
            ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now))
            ->count();

        return $autoRunning > 1
            ? ["Ada {$autoRunning} diskon otomatis (tanpa kode) yang sedang berlaku bersamaan. Pastikan logika checkout memilih yang benar, atau nonaktifkan salah satunya."]
            : [];
    }

    private function validated(Request $request, ?SubscriptionDiscount $discount = null): array
    {
        // Kode voucher disimpan huruf besar tanpa spasi; kosong = diskon otomatis
        $code = strtoupper(preg_replace('/\s+/', '', (string) $request->input('code')));
        $request->merge(['code' => $code !== '' ? $code : null]);

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'code'                => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('subscription_discounts', 'code')->ignore($discount?->id)],
            'discount_type'       => 'required|in:percent,fixed',
            'discount_value'      => 'required|numeric|gt:0',
            'max_discount_amount' => 'nullable|numeric|min:1',
            'plan_id'             => 'nullable|exists:subscription_plans,id',
            'valid_from'          => 'nullable|date',
            'valid_until'         => 'nullable|date|after_or_equal:valid_from',
            'max_usage'           => 'nullable|integer|min:1',
        ], [
            'code.regex' => 'Kode hanya boleh huruf, angka, tanda minus (-) dan garis bawah (_).',
        ], [
            'name'                => 'nama voucher',
            'code'                => 'kode voucher',
            'discount_type'       => 'tipe diskon',
            'discount_value'      => 'nilai diskon',
            'max_discount_amount' => 'maksimal potongan',
            'plan_id'             => 'paket',
            'valid_from'          => 'tanggal mulai',
            'valid_until'         => 'tanggal berakhir',
            'max_usage'           => 'batas pemakaian',
        ]);

        if ($data['discount_type'] === 'percent') {
            if ($data['discount_value'] > 100) {
                throw ValidationException::withMessages(['discount_value' => 'Diskon persen maksimal 100%.']);
            }
        } else {
            // Maks potongan hanya relevan untuk tipe persen
            $data['max_discount_amount'] = null;

            // Potongan nominal tidak boleh melebihi harga paket yang dipilih
            if (!empty($data['plan_id'])) {
                $plan = SubscriptionPlan::find($data['plan_id']);
                if ($plan && !$plan->is_free && $data['discount_value'] >= $plan->price) {
                    throw ValidationException::withMessages([
                        'discount_value' => 'Potongan Rp ' . number_format($data['discount_value'], 0, ',', '.')
                            . ' sama atau melebihi harga paket ' . $plan->name . ' (Rp ' . number_format($plan->price, 0, ',', '.') . ').',
                    ]);
                }
            }
        }

        // Tanggal: mulai jam 00:00, berakhir sampai 23:59:59 (sebelumnya voucher berakhir di awal hari)
        $data['valid_from']  = !empty($data['valid_from']) ? Carbon::parse($data['valid_from'])->startOfDay() : null;
        $data['valid_until'] = !empty($data['valid_until']) ? Carbon::parse($data['valid_until'])->endOfDay() : null;

        $data['plan_id']   = $data['plan_id'] ?? null;
        $data['max_usage'] = $data['max_usage'] ?? null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
