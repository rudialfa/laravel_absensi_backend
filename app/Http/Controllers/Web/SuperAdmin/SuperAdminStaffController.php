<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('role', 'superadmin')->whereNull('company_id');

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $staff = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('pages.superadmin.staff.index', compact('staff'));
    }

    public function create()
    {
        return view('pages.superadmin.staff.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $staff = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'password'   => Hash::make($data['password']),
            'role'       => 'superadmin',
            'company_id' => null,
        ]);

        AuditLog::record('create_staff', $staff, "Staff superadmin {$staff->name} dibuat");

        return redirect()
            ->route('pages.superadmin.staff.index')
            ->with('success', 'Staff superadmin berhasil dibuat.');
    }

    public function edit($id)
    {
        $staff = User::where('role', 'superadmin')->whereNull('company_id')->findOrFail($id);

        return view('pages.superadmin.staff.edit', compact('staff'));
    }

    public function update(Request $request, $id)
    {
        $staff = User::where('role', 'superadmin')->whereNull('company_id')->findOrFail($id);

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $staff->id,
            'phone'    => 'nullable|string|max:30',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $staff->name  = $data['name'];
        $staff->email = $data['email'];
        $staff->phone = $data['phone'] ?? null;

        if (!empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
        }

        $staff->save();

        AuditLog::record('update_staff', $staff, "Staff superadmin {$staff->name} diperbarui");

        return redirect()
            ->route('superadmin.staff.index')
            ->with('success', 'Staff superadmin berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $staff = User::where('role', 'superadmin')->whereNull('company_id')->findOrFail($id);

        if ($staff->id === auth()->id()) {
            return redirect()
                ->route('superadmin.staff.index')
                ->with('error', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        $name = $staff->name;
        $staff->delete();

        AuditLog::record('delete_staff', null, "Staff superadmin {$name} (ID: {$id}) dihapus");

        return redirect()
            ->route('superadmin.staff.index')
            ->with('success', 'Staff superadmin berhasil dihapus.');
    }
}
