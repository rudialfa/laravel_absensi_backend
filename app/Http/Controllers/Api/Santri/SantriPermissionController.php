<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SantriPermissionController extends Controller
{
    // // ============================================================
    // // PRIVATE HELPERS
    // // ============================================================

    // private function ensureSantri(): void
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'santri') {
    //         abort(response()->json([
    //             'status'  => false,
    //             'message' => 'Akses ditolak (khusus Santri)',
    //         ], 403));
    //     }
    // }

    // private function companyId(): int
    // {
    //     $companyId = auth()->user()->company_id ?? null;
    //     if (!$companyId) {
    //         abort(response()->json([
    //             'status'  => false,
    //             'message' => 'Company ID tidak ditemukan',
    //         ], 422));
    //     }
    //     return (int) $companyId;
    // }

    // // ============================================================
    // // INDEX — GET /api/pesantren/santri/permissions
    // // Sejajar: EmployeePermissionController::index()
    // // ============================================================
    // public function index(): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $data = Permission::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->orderByDesc('id')
    //         ->paginate(20);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'List izin santri',
    //         'data'    => $data,
    //     ]);
    // }

    // // ============================================================
    // // STORE — POST /api/pesantren/santri/permissions
    // // Sejajar: EmployeePermissionController::store()
    // // ============================================================
    // public function store(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $validated = $request->validate([
    //         'date_permission' => 'required|date',
    //         'reason'          => 'required|string|max:500',
    //         'image'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    //     ]);

    //     $imagePath = null;

    //     if ($request->hasFile('image')) {
    //         $destinationPath = public_path('image/permission');
    //         if (!File::exists($destinationPath)) {
    //             File::makeDirectory($destinationPath, 0755, true);
    //         }
    //         $file      = $request->file('image');
    //         $fileName  = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    //         $file->move($destinationPath, $fileName);
    //         $imagePath = 'image/permission/' . $fileName;
    //     }

    //     $perm = Permission::create([
    //         'company_id'      => $this->companyId(),
    //         'user_id'         => auth()->id(),
    //         'date_permission' => $validated['date_permission'],
    //         'reason'          => $validated['reason'],
    //         'image'           => $imagePath,
    //         'is_approved'     => null, // pending
    //     ]);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Izin berhasil diajukan',
    //         'data'    => $perm,
    //     ], 201);
    // }

    // // ============================================================
    // // SHOW — GET /api/pesantren/santri/permissions/{id}
    // // Sejajar: EmployeePermissionController::show()
    // // ============================================================
    // public function show(int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $perm = Permission::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Detail izin',
    //         'data'    => $perm,
    //     ]);
    // }

    // // ============================================================
    // // CANCEL — POST /api/pesantren/santri/permissions/{id}/cancel
    // // Sejajar: EmployeePermissionController::cancel()
    // // ============================================================
    // public function cancel(int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $perm = Permission::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     if ($perm->is_approved === true) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Tidak bisa cancel, izin sudah disetujui',
    //         ], 422);
    //     }

    //     // Hapus file image jika ada
    //     if ($perm->image && File::exists(public_path($perm->image))) {
    //         File::delete(public_path($perm->image));
    //     }

    //     $perm->delete();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Izin berhasil dibatalkan',
    //     ]);
    // }

    // kode 2

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Santri)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Company ID tidak ditemukan',
            ], 422));
        }
        return (int) $companyId;
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri/permissions
    // Sejajar: EmployeePermissionController::index()
    // ============================================================
    public function index(): JsonResponse
    {
        $this->ensureSantri();

        $data = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'List izin santri',
            'data'    => $data,
        ]);
    }

    // ============================================================
    // STORE — POST /api/pesantren/santri/permissions
    // Sejajar: EmployeePermissionController::store()
    // ============================================================
    public function store(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $validated = $request->validate([
            'date_permission' => 'required|date',
            'reason'          => 'required|string|max:500',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $destinationPath = public_path('image/permission');
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            $file      = $request->file('image');
            $fileName  = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);
            $imagePath = 'image/permission/' . $fileName;
        }

        $perm = Permission::create([
            'company_id'      => $this->companyId(),
            'user_id'         => auth()->id(),
            'date_permission' => $validated['date_permission'],
            'reason'          => $validated['reason'],
            'image'           => $imagePath,
            'is_approved'     => null, // pending
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Izin berhasil diajukan',
            'data'    => $perm,
        ], 201);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/permissions/{id}
    // Sejajar: EmployeePermissionController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail izin',
            'data'    => $perm,
        ]);
    }

    // ============================================================
    // CANCEL — POST /api/pesantren/santri/permissions/{id}/cancel
    // Sejajar: EmployeePermissionController::cancel()
    // ============================================================
    public function cancel(int $id): JsonResponse
    {
        $this->ensureSantri();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // Tidak bisa cancel jika sudah disetujui
        if ($perm->is_approved === true) {
            return response()->json([
                'status'  => false,
                'message' => 'Tidak bisa cancel, izin sudah disetujui',
            ], 422);
        }

        // Hapus file image jika ada
        if ($perm->image && File::exists(public_path($perm->image))) {
            File::delete(public_path($perm->image));
        }

        $perm->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Izin berhasil dibatalkan',
        ]);
    }
}
