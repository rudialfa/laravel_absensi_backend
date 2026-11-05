<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manajemen SEMUA pengguna tenant (HR, karyawan, admin sekolah, guru, wali, ustadz, santri).
 * Akun superadmin/staff internal TIDAK di sini — dikelola di menu Staff Superadmin.
 */
class SuperAdminUserController extends Controller
{
    /** Role yang boleh dipakai per jenis lembaga */
    public const ROLES_BY_TYPE = [
        'company'   => ['hr', 'employee'],
        'school'    => ['admin', 'guru', 'wali'],
        'pesantren' => ['ustadz', 'santri'],
    ];

    public const ROLE_LABELS = [
        'hr'       => 'HR',
        'employee' => 'Karyawan',
        'admin'    => 'Admin Sekolah',
        'guru'     => 'Guru',
        'wali'     => 'Wali Murid',
        'ustadz'   => 'Ustadz',
        'santri'   => 'Santri',
        'company'  => 'Company (lama)',
        'user'     => 'User (lama)',
    ];

    /** Role pengelola (penanggung jawab tenant) per jenis lembaga */
    public const ADMIN_ROLE_BY_TYPE = [
        'company'   => 'hr',
        'school'    => 'admin',
        'pesantren' => 'ustadz',
    ];

    /** Role internal yang tidak dikelola dari menu ini */
    private const INTERNAL_ROLES = ['superadmin', 'staff'];

    private function baseQuery()
    {
        return User::whereNotIn('role', self::INTERNAL_ROLES);
    }

    private function findUser($id): User
    {
        return $this->baseQuery()->with('company')->findOrFail($id);
    }

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        // ?search= (dan ?q= dari Global Search)
        $search = trim((string) $request->input('search', $request->input('q', '')));

        $query = $this->baseQuery()->with('company:id,name,type');

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($request->filled('company_id')) {
            $request->company_id === 'none'
                ? $query->whereNull('company_id')
                : $query->where('company_id', $request->company_id);
        }

        if ($request->filled('type')) {
            $query->whereHas('company', fn($q) => $q->where('type', $request->type));
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        match ($request->input('sort', 'newest')) {
            'name'   => $query->orderBy('name'),
            'oldest' => $query->orderBy('id'),
            default  => $query->orderByDesc('id'),
        };

        $users = $query->paginate(20)->withQueryString();

        $roleCounts = $this->baseQuery()
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('pages.superadmin.users.index', [
            'users'          => $users,
            'search'         => $search,
            'companies'      => Company::orderBy('name')->get(['id', 'name', 'type']),
            'selectedTenant' => $request->filled('company_id') && $request->company_id !== 'none'
                ? Company::find($request->company_id)
                : null,
            'roleLabels'     => self::ROLE_LABELS,
            'roleCounts'     => $roleCounts,
            'typeLabels'     => SuperAdminTenantController::SUPPORTED_TYPES,
            'totalUsers'     => $roleCounts->sum(),
            'inactiveUsers'  => $this->baseQuery()->where('is_active', false)->count(),
        ]);
    }

    // =========================================================
    // CREATE + STORE
    // =========================================================
    public function create(Request $request)
    {
        return view('pages.superadmin.users.create', $this->formData(null, $request->input('company_id')));
    }

    public function store(Request $request)
    {
        $data = $this->validateUser($request);

        $plainPassword = $data['password'] ?: Str::random(10);

        $user = new User();
        $user->forceFill(array_merge($data, [
            'password'  => Hash::make($plainPassword),
            'image_url' => $request->hasFile('image') ? $this->storeImage($request) : null,
        ]))->save();

        AuditLog::record('create_user', $user, "Pengguna {$user->email} ({$user->role}) dibuat di tenant {$user->company?->name}");

        $redirect = redirect()
            ->route('superadmin.users.show', $user->id)
            ->with('success', "Pengguna {$user->name} berhasil dibuat.");

        // Password acak hanya ditampilkan SEKALI
        if (empty($data['password'])) {
            $redirect->with('generated_password', $plainPassword);
        }

        return $redirect;
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $user = $this->findUser($id);

        $tokens = $user->tokens();
        $session = [
            'count'     => (clone $tokens)->count(),
            'last_used' => (clone $tokens)->max('last_used_at'),
        ];

        // Ringkasan aktivitas sesuai peran
        $activity = [];

        if (in_array($user->role, ['hr', 'employee'], true)) {
            $activity['Total absensi'] = $user->attendances()->count();
            $lastAttendance = $user->attendances()->latest('date')->first();
            $activity['Absen terakhir'] = $lastAttendance
                ? \Carbon\Carbon::parse($lastAttendance->date)->format('d M Y') . ' (' . substr($lastAttendance->time_in, 0, 5) . ')'
                : '-';
            $activity['Laporan harian'] = $user->dailyReports()->count();
        }

        if ($user->role === 'guru') {
            $activity['Kelas diampu'] = $user->teachingClasses()->count();
            $activity['Wali kelas'] = $user->homeroomClasses()->count();
        }

        if ($user->role === 'ustadz') {
            $activity['Sesi mutaba\'ah diajar'] = $user->mutabaahSebagaiUstadz()->count();
        }

        if ($user->role === 'santri') {
            $activity['Sesi mutaba\'ah'] = $user->mutabaahSebagaiSantri()->count();
        }

        $children = $user->role === 'wali'
            ? $user->guardedStudents()->get()
            : collect();

        return view('pages.superadmin.users.show', [
            'user'       => $user,
            'session'    => $session,
            'activity'   => $activity,
            'children'   => $children,
            'roleLabels' => self::ROLE_LABELS,
            'typeLabels' => SuperAdminTenantController::SUPPORTED_TYPES,
            'isLastAdmin' => $this->isLastActiveAdmin($user),
        ]);
    }

    // =========================================================
    // EDIT + UPDATE
    // =========================================================
    public function edit($id)
    {
        $user = $this->findUser($id);

        return view('pages.superadmin.users.edit', array_merge(
            $this->formData($user),
            ['user' => $user]
        ));
    }

    public function update(Request $request, $id)
    {
        $user = $this->findUser($id);
        $data = $this->validateUser($request, $user);

        // Jangan sampai tenant kehilangan admin aktif terakhirnya
        $losingAdmin = $this->isLastActiveAdmin($user) && (
            $data['role'] !== $user->role
            || (int) $data['company_id'] !== (int) $user->company_id
            || !$data['is_active']
        );

        if ($losingAdmin) {
            return back()->withInput()->with(
                'error',
                'Ini satu-satunya akun pengelola aktif di tenant tersebut. Buat akun pengelola lain dulu sebelum mengubah peran, tenant, atau menonaktifkannya.'
            );
        }

        $before = $user->only(['name', 'email', 'phone', 'role', 'company_id', 'position', 'department', 'salary', 'is_active']);

        $password = $data['password'];
        unset($data['password']);

        if ($password) {
            $data['password'] = Hash::make($password);
        }

        if ($request->boolean('remove_image')) {
            $this->deleteImage($user->image_url);
            $data['image_url'] = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($user->image_url);
            $data['image_url'] = $this->storeImage($request);
        }

        $user->forceFill($data)->save();

        // Ganti password / nonaktif / pindah tenant → paksa login ulang di aplikasi
        if ($password || !$user->is_active || $before['company_id'] != $user->company_id) {
            $user->tokens()->delete();
        }

        AuditLog::record('update_user', $user, "Data pengguna {$user->email} diperbarui", [
            'before' => $before,
            'after'  => $user->only(array_keys($before)),
            'password_changed' => (bool) $password,
        ]);

        return redirect()
            ->route('superadmin.users.show', $user->id)
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    // =========================================================
    // AKSI CEPAT
    // =========================================================

    /** Aktif ↔ nonaktif. Nonaktif = keluar paksa dari aplikasi. */
    public function toggleActive($id)
    {
        $user = $this->findUser($id);

        if ($user->is_active && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Tidak bisa menonaktifkan satu-satunya akun pengelola aktif di tenant ini.');
        }

        $user->forceFill(['is_active' => !$user->is_active])->save();

        if (!$user->is_active) {
            $user->tokens()->delete();
            $user->forceFill(['fcm_token' => null])->save();
        }

        AuditLog::record(
            $user->is_active ? 'activate_user' : 'deactivate_user',
            $user,
            'Pengguna ' . $user->email . ($user->is_active ? ' diaktifkan' : ' dinonaktifkan')
        );

        return back()->with('success', 'Pengguna ' . ($user->is_active ? 'diaktifkan.' : 'dinonaktifkan dan dikeluarkan dari aplikasi.'));
    }

    /** Reset password. Kosong = dibuatkan acak & ditampilkan sekali. */
    public function resetPassword(Request $request, $id)
    {
        $user = $this->findUser($id);

        $request->validate([
            'new_password' => 'nullable|string|min:6|confirmed',
        ], [], ['new_password' => 'password baru']);

        $plain = $request->input('new_password') ?: Str::random(10);

        $user->forceFill(['password' => Hash::make($plain)])->save();
        $user->tokens()->delete();

        AuditLog::record('reset_password_user', $user, "Password {$user->email} direset oleh superadmin");

        $redirect = redirect()
            ->route('superadmin.users.show', $user->id)
            ->with('success', 'Password berhasil direset. Pengguna harus login ulang di aplikasi.');

        if (!$request->filled('new_password')) {
            $redirect->with('generated_password', $plain);
        }

        return $redirect;
    }

    /** Keluarkan dari semua perangkat + lepas token notifikasi */
    public function logoutAll($id)
    {
        $user  = $this->findUser($id);
        $count = $user->tokens()->count();

        $user->tokens()->delete();
        $user->forceFill(['fcm_token' => null])->save();

        AuditLog::record('logout_user', $user, "Semua sesi {$user->email} dikeluarkan ({$count} perangkat)");

        return back()->with('success', "{$count} sesi login dikeluarkan. Pengguna harus login ulang.");
    }

    // =========================================================
    // DESTROY — hapus permanen, wajib ketik email
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $user = $this->findUser($id);

        $request->validate(['confirm_email' => 'required|string']);

        if (trim($request->confirm_email) !== $user->email) {
            return back()->with('error', 'Email yang diketik tidak sama. Pengguna TIDAK dihapus.');
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->with('error', 'Tidak bisa menghapus satu-satunya akun pengelola aktif di tenant ini.');
        }

        // Banyak tabel sekolah memakai FK cascade ke users: menghapus guru/wali akan IKUT menghapus
        // nilai, jadwal, materi, tugas, izin murid, dll. Tolak dan sarankan nonaktifkan.
        $usage = array_filter($this->cascadeUsage($user));
        if ($usage) {
            $list = collect($usage)->map(fn($n, $label) => "{$n} {$label}")->implode(', ');

            return back()->with('error', "Pengguna ini masih punya data yang akan IKUT TERHAPUS bila akunnya dihapus ({$list}). Nonaktifkan akunnya saja.");
        }

        $info = $user->only(['id', 'name', 'email', 'role', 'company_id']);
        $image = $user->image_url;

        try {
            DB::transaction(function () use ($user) {
                $user->tokens()->delete();
                $user->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Pengguna gagal dihapus karena masih terhubung dengan data lain. Nonaktifkan saja akunnya. (' . $e->getMessage() . ')');
        }

        $this->deleteImage($image);

        AuditLog::record('delete_user', null, "Pengguna {$info['email']} ({$info['role']}) dihapus permanen", $info);

        return redirect()
            ->route('superadmin.users.index', $info['company_id'] ? ['company_id' => $info['company_id']] : [])
            ->with('success', "Pengguna {$info['name']} berhasil dihapus.");
    }

    // =========================================================
    // HELPER
    // =========================================================

    private function formData(?User $user = null, $presetCompanyId = null): array
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'type']);

        $rolesByType = self::ROLES_BY_TYPE;

        // Role lama (user/company) tetap bisa dipilih saat edit supaya tidak berubah diam-diam
        if ($user && $user->company && !in_array($user->role, $rolesByType[$user->company->type] ?? [], true)) {
            $rolesByType[$user->company->type][] = $user->role;
        }

        return [
            'companies'       => $companies,
            'rolesByType'     => $rolesByType,
            'roleLabels'      => self::ROLE_LABELS,
            'typeLabels'      => SuperAdminTenantController::SUPPORTED_TYPES,
            'presetCompanyId' => $presetCompanyId,
        ];
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $company = Company::find($request->input('company_id'));
        $allowedRoles = $company ? (self::ROLES_BY_TYPE[$company->type] ?? []) : [];

        if ($user && $company && (int) $company->id === (int) $user->company_id) {
            $allowedRoles[] = $user->role; // role lama tetap valid
        }

        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'role'       => ['required', Rule::in(array_unique($allowedRoles))],
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone'      => 'nullable|string|max:20',
            'position'   => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'salary'     => 'nullable|numeric|min:0|max:9999999999',
            'password'   => 'nullable|string|min:6|confirmed', // kosong saat tambah = dibuatkan acak
            'image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'role.in' => 'Peran tidak sesuai dengan jenis lembaga yang dipilih.',
        ], [
            'company_id' => 'tenant',
            'role'       => 'peran',
            'name'       => 'nama',
            'phone'      => 'no. HP',
            'position'   => 'jabatan',
            'department' => 'departemen',
            'salary'     => 'gaji pokok',
            'image'      => 'foto',
        ]);

        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active');
        $data['password']  = $data['password'] ?? null;

        // Jabatan, departemen & gaji hanya relevan untuk perusahaan
        if (($company->type ?? null) !== 'company') {
            $data['salary'] = $user->salary ?? 0;
        } else {
            $data['salary'] = $data['salary'] ?? 0;
        }

        return $data;
    }

    /** Apakah user ini satu-satunya pengelola AKTIF di tenant-nya? */
    /**
     * Data yang akan IKUT TERHAPUS (FK ON DELETE CASCADE ke users.id) kalau user ini dihapus.
     * Daftar tabel dibaca otomatis dari database (information_schema), jadi tabel baru yang
     * memakai ->constrained('users')->cascadeOnDelete() ikut terdeteksi tanpa perlu ubah kode.
     * Kunci = label, nilai = jumlah baris.
     */
    private function cascadeUsage(User $user): array
    {
        // Tabel milik akun itu sendiri — wajar ikut terhapus, tidak perlu menghalangi
        $ignore = '/(token|session|password_reset|notification|fcm|device|chat|message|otp|log)/i';

        $labels = [
            'grades'                 => 'nilai murid',
            'lesson_schedules'       => 'jadwal pelajaran',
            'learning_materials'     => 'materi',
            'assignments'            => 'tugas',
            'assignment_submissions' => 'jawaban tugas',
            'academic_events'        => 'kalender akademik',
            'class_promotions'       => 'riwayat kenaikan kelas',
            'student_permissions'    => 'pengajuan izin murid',
            'class_teachers'         => 'penugasan kelas',
            'student_guardians'      => 'hubungan wali-murid',
            'student_bills'          => 'tagihan murid',
            'bill_payments'          => 'pembayaran tagihan',
            'attendances'            => 'absensi',
            'leaves'                 => 'cuti',
            'permissions'            => 'izin karyawan',
            'overtime_requests'      => 'lembur',
            'payrools'               => 'payroll',
            'loans'                  => 'pinjaman',
            'daily_reports'          => 'laporan harian',
            'monthly_reports'        => 'laporan bulanan',
        ];

        try {
            $fks = DB::table('information_schema.KEY_COLUMN_USAGE as k')
                ->join('information_schema.REFERENTIAL_CONSTRAINTS as r', function ($j) {
                    $j->on('r.CONSTRAINT_SCHEMA', '=', 'k.CONSTRAINT_SCHEMA')
                        ->on('r.CONSTRAINT_NAME', '=', 'k.CONSTRAINT_NAME');
                })
                ->where('k.CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))
                ->where('k.REFERENCED_TABLE_NAME', 'users')
                ->where('k.REFERENCED_COLUMN_NAME', 'id')
                ->where('r.DELETE_RULE', 'CASCADE')
                ->get(['k.TABLE_NAME as table_name', 'k.COLUMN_NAME as column_name']);
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($fks as $fk) {
            if (preg_match($ignore, $fk->table_name)) {
                continue;
            }

            $count = DB::table($fk->table_name)->where($fk->column_name, $user->id)->count();
            if ($count > 0) {
                $label = $labels[$fk->table_name] ?? str_replace('_', ' ', $fk->table_name);
                $result[$label] = ($result[$label] ?? 0) + $count;
            }
        }

        return $result;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        if (!$user->company || !$user->is_active) {
            return false;
        }

        $adminRole = self::ADMIN_ROLE_BY_TYPE[$user->company->type] ?? null;

        if (!$adminRole || $user->role !== $adminRole) {
            return false;
        }

        return !User::where('company_id', $user->company_id)
            ->where('role', $adminRole)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->exists();
    }

    /** Foto profil disimpan di public/image/profile (sama dengan AuthController::update) */
    private function storeImage(Request $request): string
    {
        $destination = public_path('image/profile');

        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $file     = $request->file('image');
        $fileName = time() . '_' . $file->hashName();
        $file->move($destination, $fileName);

        return 'image/profile/' . $fileName;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && !str_starts_with($path, 'http') && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }
}
