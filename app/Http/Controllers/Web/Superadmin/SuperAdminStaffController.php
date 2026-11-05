<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Akun internal pengelola panel (role superadmin, tanpa tenant).
 */
class SuperAdminStaffController extends Controller
{
    private function baseQuery()
    {
        return User::where('role', 'superadmin')->whereNull('company_id');
    }

    private function findStaff($id): User
    {
        return $this->baseQuery()->findOrFail($id);
    }

    /** Jumlah superadmin aktif selain $exceptId */
    private function otherActiveCount(int $exceptId): int
    {
        return $this->baseQuery()
            ->where('is_active', true)
            ->where('id', '!=', $exceptId)
            ->count();
    }

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        // ?name= tetap didukung (link lama)
        $search = trim((string) $request->input('search', $request->input('name', '')));

        $query = $this->baseQuery();

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $staff = $query->orderByDesc('is_active')->orderBy('name')->paginate(15)->withQueryString();

        // Aktivitas terakhir tiap staff (dari audit log)
        $lastActivity = AuditLog::whereIn('user_id', $staff->pluck('id'))
            ->select('user_id', DB::raw('max(created_at) as last_at'), DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return view('pages.superadmin.staff.index', [
            'staff'        => $staff,
            'search'       => $search,
            'lastActivity' => $lastActivity,
            'totalActive'  => $this->baseQuery()->where('is_active', true)->count(),
            'totalAll'     => $this->baseQuery()->count(),
        ]);
    }

    // =========================================================
    // CREATE + STORE
    // =========================================================
    public function create()
    {
        return view('pages.superadmin.staff.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateStaff($request);

        $staff = new User();
        $staff->forceFill([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'password'   => Hash::make($data['password']),
            'role'       => 'superadmin',
            'company_id' => null,
            'is_active'  => $request->boolean('is_active', true),
        ])->save();

        AuditLog::record('create_staff', $staff, "Staff superadmin {$staff->email} dibuat");

        // FIX: sebelumnya ->route('pages.superadmin.staff.index') → error "Route not defined"
        return redirect()
            ->route('superadmin.staff.show', $staff->id)
            ->with('success', "Staff {$staff->name} berhasil dibuat dan sudah bisa login ke panel.");
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $staff = $this->findStaff($id);

        $logs = AuditLog::where('user_id', $staff->id)
            ->latest()
            ->limit(20)
            ->get();

        $actionCounts = AuditLog::where('user_id', $staff->id)
            ->select('action', DB::raw('count(*) as total'))
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'action');

        return view('pages.superadmin.staff.show', [
            'staff'        => $staff,
            'logs'         => $logs,
            'actionCounts' => $actionCounts,
            'totalLogs'    => $actionCounts->sum(),
            'isSelf'       => $staff->id === auth()->id(),
            'isLastActive' => $staff->is_active && $this->otherActiveCount($staff->id) === 0,
        ]);
    }

    // =========================================================
    // EDIT + UPDATE
    // =========================================================
    public function edit($id)
    {
        $staff = $this->findStaff($id);

        return view('pages.superadmin.staff.edit', [
            'staff'  => $staff,
            'isSelf' => $staff->id === auth()->id(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $staff  = $this->findStaff($id);
        $data   = $this->validateStaff($request, $staff);
        $isSelf = $staff->id === auth()->id();

        // Akun sendiri tidak bisa dinonaktifkan dari form (selalu tetap aktif)
        $isActive = $isSelf ? true : $request->boolean('is_active');

        if (!$isActive && $staff->is_active && $this->otherActiveCount($staff->id) === 0) {
            return back()->withInput()->with('error', 'Minimal harus ada 1 superadmin aktif.');
        }

        $before = $staff->only(['name', 'email', 'phone', 'is_active']);

        $staff->name      = $data['name'];
        $staff->email     = $data['email'];
        $staff->phone     = $data['phone'] ?? null;
        $staff->is_active = $isActive;

        $passwordChanged = !empty($data['password']);
        if ($passwordChanged) {
            $staff->password = Hash::make($data['password']);
        }

        $staff->save();

        AuditLog::record('update_staff', $staff, "Staff superadmin {$staff->email} diperbarui", [
            'before'           => $before,
            'after'            => $staff->only(array_keys($before)),
            'password_changed' => $passwordChanged,
        ]);

        return redirect()
            ->route('superadmin.staff.show', $staff->id)
            ->with('success', 'Data staff berhasil diperbarui.'
                . ($passwordChanged && $isSelf ? ' Gunakan password baru saat login berikutnya.' : ''));
    }

    // =========================================================
    // AKTIF / NONAKTIF
    // =========================================================
    public function toggleActive($id)
    {
        $staff = $this->findStaff($id);

        if ($staff->id === auth()->id()) {
            return back()->with('error', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
        }

        if ($staff->is_active && $this->otherActiveCount($staff->id) === 0) {
            return back()->with('error', 'Minimal harus ada 1 superadmin aktif.');
        }

        $staff->forceFill(['is_active' => !$staff->is_active])->save();

        AuditLog::record(
            $staff->is_active ? 'activate_staff' : 'deactivate_staff',
            $staff,
            'Staff superadmin ' . $staff->email . ($staff->is_active ? ' diaktifkan' : ' dinonaktifkan')
        );

        return back()->with('success', $staff->is_active
            ? 'Staff diaktifkan kembali.'
            : 'Staff dinonaktifkan. Sesi login di panel akan berakhir pada akses berikutnya.');
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $staff = $this->findStaff($id);

        if ($staff->id === auth()->id()) {
            return back()->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        if ($staff->is_active && $this->otherActiveCount($staff->id) === 0) {
            return back()->with('error', 'Minimal harus ada 1 superadmin aktif.');
        }

        $request->validate(['confirm_email' => 'required|string']);

        if (trim($request->confirm_email) !== $staff->email) {
            return back()->with('error', 'Email yang diketik tidak sama. Staff TIDAK dihapus.');
        }

        $info = $staff->only(['id', 'name', 'email']);

        $staff->tokens()->delete();
        $staff->delete(); // audit_logs.user_id → null (nullOnDelete), riwayat tetap ada

        AuditLog::record('delete_staff', null, "Staff superadmin {$info['email']} dihapus", $info);

        return redirect()
            ->route('superadmin.staff.index')
            ->with('success', "Staff {$info['name']} berhasil dihapus.");
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function validateStaff(Request $request, ?User $staff = null): array
    {
        return $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($staff?->id)],
            'phone'    => 'nullable|string|max:30',
            'password' => ($staff ? 'nullable' : 'required') . '|string|min:8|confirmed',
        ], [], [
            'name'     => 'nama',
            'phone'    => 'no. HP',
        ]);
    }
}
