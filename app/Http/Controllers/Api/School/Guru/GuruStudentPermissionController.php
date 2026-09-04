<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\StudentPermission;
use Illuminate\Http\Request;

class GuruStudentPermissionController extends Controller
{
    /**
     * GET /api/school/guru/permissions
     * Pengajuan izin/sakit dari murid-murid di kelas yang diampu guru ini.
     */
    public function index(Request $request)
    {
        $classIds = $request->user()->teachingClasses()->pluck('class_rooms.id');

        $permissions = StudentPermission::whereHas(
            'student',
            fn($q) => $q->whereIn('class_id', $classIds)
        )
            ->with(['student:id,name,class_id', 'submittedBy:id,name'])
            ->where('status', 'pending')
            ->latest('date_permission')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data pengajuan izin berhasil diambil',
            'data'    => $permissions,
        ]);
    }

    /**
     * PATCH /api/school/guru/permissions/{permission}/review
     * Approve/reject pengajuan izin. Kalau approved, otomatis
     * bikin/update baris StudentAttendance untuk tanggal terkait.
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
