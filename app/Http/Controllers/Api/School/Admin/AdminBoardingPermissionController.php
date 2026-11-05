<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardingPermission;
use Illuminate\Http\Request;

class AdminBoardingPermissionController extends Controller
{
    /**
     * GET /api/school/admin/boarding-permissions?status=
     */
    public function index(Request $request)
    {
        $permissions = BoardingPermission::where('company_id', $request->user()->company_id)
            ->with(['student:id,name,class_id', 'student.classRoom:id,name', 'submittedBy:id,name', 'reviewedBy:id,name'])
            ->when($request->query('status'), fn($q, $status) => $q->where('status', $status))
            ->latest('tanggal_keluar')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Data izin keluar/pulang berhasil diambil',
            'data'    => $permissions,
        ]);
    }

    /**
     * PATCH /api/school/admin/boarding-permissions/{permission}/review
     */
    public function review(Request $request, BoardingPermission $permission)
    {
        $this->authorize('review', $permission);

        abort_unless($permission->status === 'pending', 422, 'Pengajuan ini sudah pernah direview');

        $data = $request->validate([
            'status'          => 'required|in:approved,rejected',
            'catatan_review'  => 'nullable|string',
        ]);

        $permission->update([
            'status'         => $data['status'],
            'catatan_review' => $data['catatan_review'] ?? null,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => $data['status'] === 'approved'
                ? 'Izin disetujui'
                : 'Izin ditolak',
            'data'    => $permission->fresh()->load(['student:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']),
        ]);
    }

    /**
     * PATCH /api/school/admin/boarding-permissions/{permission}/checkin
     * Tandai santri sudah kembali ke pondok — cuma bisa untuk izin yang
     * statusnya sudah 'approved'.
     */
    public function checkin(Request $request, BoardingPermission $permission)
    {
        $this->authorize('review', $permission);

        abort_unless($permission->status === 'approved', 422, 'Izin ini belum disetujui atau sudah selesai');

        $data = $request->validate([
            // Tanggal kembali tidak boleh sebelum tanggal keluar & tidak boleh di masa depan
            'tanggal_kembali_aktual' => 'nullable|date|after_or_equal:' . $permission->tanggal_keluar->toDateString() . '|before_or_equal:today',
        ], [
            'tanggal_kembali_aktual.after_or_equal'  => 'Tanggal kembali tidak boleh sebelum tanggal keluar',
            'tanggal_kembali_aktual.before_or_equal' => 'Tanggal kembali tidak boleh di masa depan',
        ]);

        $permission->update([
            'status'                 => 'sudah_kembali',
            'tanggal_kembali_aktual' => $data['tanggal_kembali_aktual'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Santri berhasil ditandai sudah kembali',
            'data'    => $permission,
        ]);
    }
}
