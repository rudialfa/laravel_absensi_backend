<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class HrCompanyEmployeeController extends Controller
{

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus HR)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan pada akun HR',
            ], 422));
        }
        return (int) $companyId;
    }

    public function index(Request $request)
    {
        $this->ensureHr();

        $q = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'employee')
            ->orderByDesc('id');

        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->where(function ($w) use ($keyword) {
                $w->where('name', 'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%")
                    ->orWhere('phone', 'like', "%$keyword%");
            });
        }

        return response()->json([
            'status' => true,
            'message' => 'List employee',
            'data' => $q->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHr();

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:180|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'position' => 'nullable|string|max:120',
            'department' => 'nullable|string|max:120',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'company_id' => $this->companyId(),
            'role' => 'employee',
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'position' => $validated['position'] ?? null,
            'department' => $validated['department'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Employee dibuat',
            'data' => $user,
        ], 201);
    }

    public function show($id)
    {
        $this->ensureHr();

        $user = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'employee')
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail employee',
            'data' => $user,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $user = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'employee')
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'sometimes|nullable|string|max:30',
            'position' => 'sometimes|nullable|string|max:120',
            'department' => 'sometimes|nullable|string|max:120',
            'password' => 'sometimes|nullable|string|min:6',
        ]);

        if (array_key_exists('name', $validated)) $user->name = $validated['name'];
        if (array_key_exists('email', $validated)) $user->email = $validated['email'];
        if (array_key_exists('phone', $validated)) $user->phone = $validated['phone'];
        if (array_key_exists('position', $validated)) $user->position = $validated['position'];
        if (array_key_exists('department', $validated)) $user->department = $validated['department'];
        if (!empty($validated['password'] ?? null)) $user->password = Hash::make($validated['password']);

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Employee diupdate',
            'data' => $user,
        ]);
    }

    public function destroy($id)
    {
        $this->ensureHr();

        $user = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'employee')
            ->findOrFail($id);

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'Employee dihapus',
        ]);
    }
}
