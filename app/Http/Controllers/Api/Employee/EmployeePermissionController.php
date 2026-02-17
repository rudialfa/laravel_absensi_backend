<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\File;

class EmployeePermissionController extends Controller
{
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus employee)'
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan'
            ], 422));
        }

        return $companyId;
    }

    // =========================
    // LIST (paginate)
    // =========================
    public function index()
    {
        $this->ensureEmployee();

        $data = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'List permission employee',
            'data' => $data
        ]);
    }

    // =========================
    // STORE (UPLOAD IMAGE)
    // =========================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'date_permission' => 'required|date',
            'reason' => 'required|string|max:500',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            // folder: public/image/permission
            $destinationPath = public_path('image/permission');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move($destinationPath, $fileName);

            $imagePath = 'image/permission/' . $fileName;
        }

        $perm = Permission::create([
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'date_permission' => $validated['date_permission'],
            'reason' => $validated['reason'],
            'image' => $imagePath,
            'is_approved' => null, // ✅ pending
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Permission berhasil dibuat',
            'data' => $perm
        ], 201);
    }

    // =========================
    // DETAIL
    // =========================
    public function show($id)
    {
        $this->ensureEmployee();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail permission',
            'data' => $perm
        ]);
    }

    // =========================
    // CANCEL (hapus data + file image jika ada)
    // =========================
    public function cancel($id)
    {
        $this->ensureEmployee();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // kalau sudah approved -> tidak bisa cancel
        if ($perm->is_approved === true) {
            return response()->json([
                'status' => false,
                'message' => 'Tidak bisa cancel, permission sudah disetujui'
            ], 422);
        }

        // hapus file image jika ada
        if ($perm->image && File::exists(public_path($perm->image))) {
            File::delete(public_path($perm->image));
        }

        $perm->delete();

        return response()->json([
            'status' => true,
            'message' => 'Permission dibatalkan'
        ]);
    }
}
