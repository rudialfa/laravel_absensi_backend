<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;
use Illuminate\Support\Facades\File;

class SantriPermissionController extends Controller
{
    private function ensureSantri()
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus santri)'
            ], 403));
        }
    }

    private function companyId()
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
    // LIST
    // =========================
    public function index()
    {
        $this->ensureSantri();

        $data = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'List permission santri',
            'data' => $data
        ]);
    }

    // =========================
    // STORE (UPLOAD IMAGE)
    // =========================
    public function store(Request $request)
    {
        $this->ensureSantri();

        $validated = $request->validate([
            'date_permission' => 'required|date',
            'reason' => 'required|string|max:500',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {

            // folder public/image/permission
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
        $this->ensureSantri();

        $perm = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail permission',
            'data' => $perm
        ]);
    }

    // =========================
    // CANCEL
    // =========================
    public function cancel($id)
    {
        $this->ensureSantri();

        $perm = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

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
