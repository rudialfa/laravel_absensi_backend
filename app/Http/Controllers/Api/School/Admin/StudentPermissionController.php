<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\StudentPermission;
use Illuminate\Http\Request;

class StudentPermissionController extends Controller
{
    /**
     * GET /api/school/admin/permissions?status=&page=
     */
    public function index(Request $request)
    {
        $permissions = StudentPermission::whereHas(
            'student',
            fn($q) => $q->where('company_id', $request->user()->company_id)
        )
            ->with(['student:id,name,class_id', 'submittedBy:id,name'])
            ->when($request->query('status'), fn($q, $status) => $q->where('status', $status))
            ->latest('date_permission')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Data pengajuan izin berhasil diambil',
            'data'    => $permissions,
        ]);
    }

    /**
     * PATCH /api/school/admin/permissions/{permission}/review
     */
    public function review(Request $request, StudentPermission $permission)
    {
        $this->authorize('review', $permission);

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $permission->update([
            'status'      => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Kalau disetujui, otomatis catat di student_attendances hari itu
        if ($data['status'] === 'approved') {
            StudentAttendance::updateOrCreate(
                [
                    'student_id' => $permission->student_id,
                    'date'       => $permission->date_permission,
                ],
                [
                    'company_id'  => $permission->student->company_id,
                    'class_id'    => $permission->student->class_id,
                    'status'      => $permission->type,
                    'recorded_by' => $request->user()->id,
                    'notes'       => 'Otomatis dari pengajuan izin #' . $permission->id,
                ]
            );
        }

        return response()->json([
            'status'  => true,
            'message' => $data['status'] === 'approved'
                ? 'Pengajuan izin disetujui'
                : 'Pengajuan izin ditolak',
            'data'    => $permission->fresh()->load(['student:id,name,class_id', 'submittedBy:id,name']),
        ]);
    }
}
