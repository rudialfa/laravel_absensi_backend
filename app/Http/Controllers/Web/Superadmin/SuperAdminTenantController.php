<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\CompanySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SuperAdminTenantController extends Controller
{
    /**
     * Jenis lembaga yang SUDAH didukung aplikasi (sama dengan registerOrganization).
     * Company::TYPES berisi rencana tipe lain (hospital, factory, dst) yang belum ada modulnya,
     * jadi tidak ditawarkan di form supaya tidak ada tenant "nyasar".
     */
    public const SUPPORTED_TYPES = [
        'company'   => 'Perusahaan',
        'school'    => 'Sekolah',
        'pesantren' => 'Pesantren',
    ];

    /** Role akun admin pertama per jenis lembaga (sama dengan AuthController::registerOrganization) */
    public const ADMIN_ROLE_MAP = [
        'company'   => 'hr',
        'school'    => 'admin',
        'pesantren' => 'ustadz',
    ];

    public const TIMEZONES = [
        'Asia/Jakarta'  => 'WIB (Asia/Jakarta)',
        'Asia/Makassar' => 'WITA (Asia/Makassar)',
        'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
    ];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $query = Company::withCount('users')
            ->with('subscriptions.plan');

        // ?name= tetap didukung (dipakai link "Lihat semua" di Global Search)
        if (!$request->filled('search') && $request->filled('name')) {
            $request->merge(['search' => $request->name]);
        }

        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('city', 'like', $like);
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('subscription')) {
            if ($request->subscription === 'none') {
                $query->doesntHave('subscriptions');
            } else {
                $query->whereHas('subscriptions', fn($q) => $q->where('status', $request->subscription));
            }
        }

        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'name'  => $query->orderBy('name'),
            'users' => $query->orderByDesc('users_count'),
            'oldest' => $query->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        $tenants = $query->paginate(15)->withQueryString();

        return view('pages.superadmin.tenants.index', [
            'tenants'    => $tenants,
            'typeLabels' => self::SUPPORTED_TYPES,
            'stats'      => [
                'total'     => Company::count(),
                'active'    => Company::where('is_active', true)->count(),
                'suspended' => Company::where('is_active', false)->count(),
            ],
        ]);
    }

    // =========================================================
    // CREATE + STORE (tenant + akun admin pertama + trial)
    // =========================================================
    public function create()
    {
        return view('pages.superadmin.tenants.create', $this->formData());
    }

    public function store(Request $request)
    {
        [$data, $admin] = $this->validateTenant($request, null, [
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|max:255|unique:users,email',
            'admin_phone'    => 'nullable|string|max:20',
            'admin_password' => 'required|string|min:6|confirmed',
        ], [
            'admin_name'     => 'nama admin',
            'admin_email'    => 'email admin',
            'admin_phone'    => 'no. HP admin',
            'admin_password' => 'password admin',
        ]);

        $logoPath = $request->hasFile('logo') ? $this->storeLogo($request) : null;

        DB::beginTransaction();

        try {
            $tenant = new Company();
            $tenant->forceFill($data);
            $tenant->image_url = $logoPath;
            $tenant->save();

            $adminUser = new User();
            $adminUser->forceFill([
                'name'       => $admin['admin_name'],
                'email'      => $admin['admin_email'],
                'phone'      => $admin['admin_phone'] ?? null,
                'password'   => Hash::make($admin['admin_password']),
                'role'       => self::ADMIN_ROLE_MAP[$tenant->type] ?? 'hr',
                'company_id' => $tenant->id,
                'is_active'  => true,
            ])->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->deleteLogo($logoPath);
            Log::error('Gagal membuat tenant dari panel superadmin', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Tenant gagal dibuat: ' . $e->getMessage());
        }

        AuditLog::record('create_tenant', $tenant, "Tenant {$tenant->name} dibuat beserta admin {$adminUser->email}");

        // Trial otomatis (di luar transaksi: kalau paket trial belum ada, tenant tetap tersimpan)
        $message = "Tenant {$tenant->name} berhasil dibuat. Admin login dengan email {$adminUser->email}.";

        if ($request->boolean('start_trial')) {
            try {
                app(SubscriptionService::class)->startTrial($tenant);
                $message .= ' Trial sudah aktif.';
            } catch (\Throwable $e) {
                return redirect()
                    ->route('superadmin.tenants.show', $tenant->id)
                    ->with('success', $message)
                    ->with('warning', 'Trial tidak bisa dimulai: ' . $e->getMessage()
                        . ' Pastikan ada paket gratis yang aktif di menu Paket Langganan.');
            }
        }

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', $message);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $tenant = Company::with([
            'subscriptions.plan',
            'invoices' => fn($q) => $q->with('plan')->latest('issued_at')->limit(10),
        ])->withCount('users')->findOrFail($id);

        $subscription = $tenant->subscriptions->first();
        $subscription?->syncStatus();

        $usersByRole = User::where('company_id', $tenant->id)
            ->select('role', DB::raw('count(*) as total'), DB::raw('sum(is_active = 1) as active'))
            ->groupBy('role')
            ->orderByDesc('total')
            ->get();

        $adminRole = self::ADMIN_ROLE_MAP[$tenant->type] ?? $tenant->billingRole();

        $admins = User::where('company_id', $tenant->id)
            ->where('role', $adminRole)
            ->orderBy('name')
            ->limit(10)
            ->get();

        return view('pages.superadmin.tenants.show', [
            'tenant'        => $tenant,
            'subscription'  => $subscription,
            'usersByRole'   => $usersByRole,
            'admins'        => $admins,
            'adminRole'     => $adminRole,
            'typeLabels'    => self::SUPPORTED_TYPES,
            'workDaysLabel' => CompanySettings::workDaysLabel($tenant),
        ]);
    }

    // =========================================================
    // EDIT + UPDATE
    // =========================================================
    public function edit($id)
    {
        $tenant = Company::findOrFail($id);

        return view('pages.superadmin.tenants.edit', array_merge(
            $this->formData($tenant),
            ['tenant' => $tenant]
        ));
    }

    public function update(Request $request, $id)
    {
        $tenant = Company::findOrFail($id);
        [$data] = $this->validateTenant($request, $tenant);

        $before = $tenant->only(array_keys($data));

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo($tenant->image_url);
            $data['image_url'] = null;
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo($tenant->image_url);
            $data['image_url'] = $this->storeLogo($request);
        }

        $tenant->forceFill($data)->save();

        AuditLog::record(
            'update_tenant',
            $tenant,
            "Data tenant {$tenant->name} diperbarui",
            ['before' => $before, 'after' => $tenant->only(array_keys($data))]
        );

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', 'Data tenant berhasil diperbarui.');
    }

    // =========================================================
    // SUSPEND / ACTIVATE
    // =========================================================
    public function suspend(Request $request, $id)
    {
        $tenant = Company::findOrFail($id);

        $request->validate([
            'suspend_reason' => 'required|string|max:255',
        ], [], ['suspend_reason' => 'alasan']);

        $tenant->forceFill([
            'is_active'      => false,
            'suspended_at'   => now(),
            'suspend_reason' => $request->suspend_reason,
        ])->save();

        // Logout paksa semua user tenant ini dari aplikasi (sama seperti API System\CompanyController)
        $loggedOut = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', User::where('company_id', $tenant->id)->select('id'))
            ->delete();

        AuditLog::record(
            'suspend_tenant',
            $tenant,
            "Tenant {$tenant->name} disuspend: {$request->suspend_reason}",
            ['tokens_revoked' => $loggedOut]
        );

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', "Tenant disuspend. {$loggedOut} sesi login di aplikasi sudah dikeluarkan.");
    }

    public function activate($id)
    {
        $tenant = Company::findOrFail($id);

        $tenant->forceFill([
            'is_active'      => true,
            'suspended_at'   => null,
            'suspend_reason' => null,
        ])->save();

        AuditLog::record('activate_tenant', $tenant, "Tenant {$tenant->name} diaktifkan kembali");

        return redirect()
            ->route('superadmin.tenants.show', $tenant->id)
            ->with('success', 'Tenant berhasil diaktifkan kembali.');
    }

    // =========================================================
    // DESTROY — wajib ketik ulang nama tenant
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $tenant = Company::withCount('users')->findOrFail($id);

        $request->validate(['confirm_name' => 'required|string']);

        if (trim($request->confirm_name) !== $tenant->name) {
            return back()->with('error', 'Nama tenant yang diketik tidak sama. Tenant TIDAK dihapus.');
        }

        $name      = $tenant->name;
        $userCount = $tenant->users_count;
        $logo      = $tenant->image_url;

        DB::beginTransaction();

        try {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', User::where('company_id', $tenant->id)->select('id'))
                ->delete();

            $tenant->delete(); // data terkait ikut terhapus lewat cascade FK

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gagal menghapus tenant', ['tenant_id' => $id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Tenant gagal dihapus: ' . $e->getMessage());
        }

        $this->deleteLogo($logo);

        AuditLog::record('delete_tenant', null, "Tenant {$name} (ID: {$id}) dihapus beserta {$userCount} pengguna", [
            'tenant_id' => (int) $id,
            'name'      => $name,
        ]);

        return redirect()
            ->route('superadmin.tenants.index')
            ->with('success', "Tenant {$name} beserta seluruh datanya berhasil dihapus.");
    }

    // =========================================================
    // HELPER
    // =========================================================

    private function formData(?Company $tenant = null): array
    {
        $types = self::SUPPORTED_TYPES;

        // Tenant lama dengan tipe di luar daftar tetap bisa diedit tanpa berubah tipe
        if ($tenant && !isset($types[$tenant->type])) {
            $types[$tenant->type] = ucfirst($tenant->type);
        }

        return [
            'types'         => $types,
            'adminRoleMap'  => self::ADMIN_ROLE_MAP,
            'timezones'     => self::TIMEZONES,
            'dayLabels'     => CompanySettings::DAY_LABELS,
            'workDays'      => $tenant ? CompanySettings::workDays($tenant) : CompanySettings::DEFAULT_WORK_DAYS,
        ];
    }

    /**
     * Validasi form tenant (+ aturan tambahan, misal akun admin) dalam SATU kali validasi,
     * supaya semua pesan error muncul bersamaan.
     *
     * @return array{0: array, 1: array} [data tenant siap simpan, data tambahan]
     */
    private function validateTenant(Request $request, ?Company $tenant = null, array $extraRules = [], array $extraAttributes = []): array
    {
        // Jam dari DB berformat H:i:s, potong ke H:i sebelum validasi
        $request->merge([
            'time_in'  => $request->time_in ? substr($request->time_in, 0, 5) : null,
            'time_out' => $request->time_out ? substr($request->time_out, 0, 5) : null,
        ]);

        $allowedTypes = array_keys(self::SUPPORTED_TYPES);
        if ($tenant) {
            $allowedTypes[] = $tenant->type;
        }

        $validated = $request->validate(array_merge([
            'name'        => 'required|string|max:255',
            'email'       => [
                'required',
                'email',
                'max:255',
                Rule::unique('companies', 'email')->ignore($tenant?->id),
            ],
            'type'        => ['required', Rule::in(array_unique($allowedTypes))],
            'address'     => 'required|string|max:500',
            'city'        => 'nullable|string|max:100',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'radius_km'   => 'required|numeric|min:0.01|max:100',
            'time_in'     => 'required|date_format:H:i',
            'time_out'    => 'required|date_format:H:i',
            'timezone'    => ['required', Rule::in(array_keys(self::TIMEZONES))],
            'work_days'   => 'required|array|min:1',
            'work_days.*' => 'integer|between:1,7',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], $extraRules), [
            'work_days.required' => 'Pilih minimal 1 hari kerja.',
        ], [
            'name'      => 'nama lembaga',
            'address'   => 'alamat',
            'city'      => 'kota',
            'radius_km' => 'radius',
            'time_in'   => 'jam masuk',
            'time_out'  => 'jam pulang',
            'timezone'  => 'zona waktu',
            'work_days' => 'hari kerja',
            'logo'      => 'logo',
        ] + $extraAttributes);

        $extra = array_intersect_key($validated, $extraRules);
        $data  = array_diff_key($validated, $extraRules);

        $workDays = array_values(array_unique(array_map('intval', $data['work_days'])));
        sort($workDays);

        unset($data['logo'], $data['work_days']);

        $data['work_days']   = json_encode($workDays); // format sama dengan CompanySettings::save()
        $data['is_boarding'] = $data['type'] === 'school' && $request->boolean('is_boarding');

        // is_active hanya diatur dari form saat membuat tenant; setelahnya pakai tombol suspend/aktifkan
        if (!$tenant) {
            $data['is_active'] = true;
        }

        return [$data, $extra];
    }

    /** Simpan logo ke public/image/logo (sama dengan HrCompanySettingController::uploadLogo) */
    private function storeLogo(Request $request): string
    {
        $destination = public_path('image/logo');

        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $file     = $request->file('logo');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destination, $fileName);

        return 'image/logo/' . $fileName;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path && !str_starts_with($path, 'http') && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
