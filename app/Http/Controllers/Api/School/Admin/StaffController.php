<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreStaffRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    /**
     * GET /api/school/admin/staff?role=&search=&page=
     * Daftar guru/wali di sekolah ini. Filter opsional ?role=guru atau ?role=wali
     */
    public function index(Request $request)
    {
        $staff = User::where('company_id', $request->user()->company_id)
            ->whereIn('role', ['guru', 'wali'])
            ->when($request->query('role'), fn($q, $role) => $q->where('role', $role))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Data staff berhasil diambil',
            'data'    => $staff,
        ]);
    }

    /**
     * POST /api/school/admin/staff
     * Buat akun baru untuk guru atau wali.
     * Password sementara di-generate otomatis kalau tidak dikirim,
     * supaya admin bisa langsung kasih tau ke guru/wali secara manual.
     */
    public function store(StoreStaffRequest $request)
    {
        $data = $request->validated();
        $plainPassword = $data['password'] ?? Str::random(8);

        $staff = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'role'       => $data['role'],
            'password'   => Hash::make($plainPassword),
            'company_id' => $request->user()->company_id,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Akun berhasil dibuat',
            'data'    => [
                'user'               => $staff,
                'generated_password' => $data['password'] ? null : $plainPassword,
            ],
        ], 201);
    }

    /**
     * GET /api/school/admin/staff/{staff}
     */
    public function show(Request $request, User $staff)
    {
        Gate::authorize('manage-staff', $staff);

        return response()->json([
            'status'  => true,
            'message' => 'Detail staff berhasil diambil',
            'data'    => $staff,
        ]);
    }

    /**
     * PUT /api/school/admin/staff/{staff}
     */
    public function update(Request $request, User $staff)
    {
        Gate::authorize('manage-staff', $staff);

        $data = $request->validate([
            'name'  => 'sometimes|string|max:150',
            'email' => 'sometimes|email|unique:users,email,' . $staff->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $staff->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Data staff berhasil diperbarui',
            'data'    => $staff,
        ]);
    }

    /**
     * POST /api/school/admin/staff/{staff}/reset-password
     * Reset password guru/wali — dipakai kalau lupa password dan
     * gak lewat alur forgot-password email biasa.
     */
    public function resetPassword(User $staff)
    {
        Gate::authorize('manage-staff', $staff);

        $newPassword = Str::random(8);

        $staff->update(['password' => Hash::make($newPassword)]);
        $staff->tokens()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Password berhasil direset',
            'data'    => [
                'generated_password' => $newPassword,
            ],
        ]);
    }

    /**
     * DELETE /api/school/admin/staff/{staff}
     * Nonaktifkan akun (bukan hapus permanen) — cegah kehilangan histori
     * data yang sudah terhubung (absen yang di-record guru ini, dst).
     */
    public function destroy(User $staff)
    {
        Gate::authorize('manage-staff', $staff);

        $staff->tokens()->delete();
        $staff->update(['is_active' => false]);

        return response()->json([
            'status'  => true,
            'message' => 'Akun dinonaktifkan',
            'data'    => null,
        ]);
    }
}
