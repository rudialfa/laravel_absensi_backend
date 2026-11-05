<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\StudentPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Cegah review ulang: izin yang sudah disetujui lalu "ditolak" akan meninggalkan
        // absen izin/sakit yang sudah terlanjur tercatat.
        abort_unless($permission->status === 'pending', 422, 'Pengajuan ini sudah pernah diproses');

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $student = $permission->student;

        // student_attendances.class_id wajib diisi — murid tanpa kelas tidak bisa dicatat absennya
        if ($data['status'] === 'approved' && (!$student || !$student->class_id)) {
            abort(422, 'Murid belum ditempatkan di kelas, tempatkan dulu sebelum menyetujui izin');
        }

        DB::transaction(function () use ($permission, $data, $request, $student) {
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
                        'date'       => $permission->date_permission->toDateString(),
                    ],
                    [
                        'company_id'  => $student->company_id,
                        'class_id'    => $student->class_id,
                        'status'      => $permission->type,
                        'recorded_by' => $request->user()->id,
                        'notes'       => 'Otomatis dari pengajuan izin #' . $permission->id,
                    ]
                );
            }
        });

        return response()->json([
            'status'  => true,
            'message' => $data['status'] === 'approved'
                ? 'Pengajuan izin disetujui'
                : 'Pengajuan izin ditolak',
            'data'    => $permission->fresh()->load(['student:id,name,class_id', 'submittedBy:id,name']),
        ]);
    }
}
