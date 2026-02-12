<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class HrCompanyEmployeeController extends Controller
{
    private function ensureHr()
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $q = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'employee');

        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%");
            });
        }

        return response()->json([
            'status' => true,
            'message' => 'List employee',
            'data' => $q->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'employee',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Employee berhasil dibuat',
            'data' => $user,
        ], 201);
    }

    public function show($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $user = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail employee', 'data' => $user]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $user = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
        ]);

        if (array_key_exists('name', $validated)) $user->name = $validated['name'];
        if (array_key_exists('email', $validated)) $user->email = $validated['email'];
        if (!empty($validated['password'])) $user->password = Hash::make($validated['password']);

        $user->save();

        return response()->json(['status' => true, 'message' => 'Employee berhasil diupdate', 'data' => $user]);
    }

    public function destroy($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $user = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->findOrFail($id);

        $user->delete();

        return response()->json(['status' => true, 'message' => 'Employee berhasil dihapus']);
    }
}
