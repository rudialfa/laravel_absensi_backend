<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\BoardingPermission;
use App\Models\Student;
use Illuminate\Http\Request;

class WaliBoardingPermissionController extends Controller
{
    /**
     * GET /api/school/wali/boarding-permissions?student_id=
     * Riwayat pengajuan izin keluar/pulang untuk anak-anak wali ini.
     */
    public function index(Request $request)
    {
        $childIds = $request->user()->guardedStudents()->pluck('students.id');

        $permissions = BoardingPermission::whereIn('student_id', $childIds)
            ->with(['student:id,name', 'reviewedBy:id,name'])
            ->when($request->query('student_id'), fn($q, $id) => $q->where('student_id', $id))
            ->latest('tanggal_keluar')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat izin berhasil diambil',
            'data'    => $permissions,
        ]);
    }

    /**
     * POST /api/school/wali/boarding-permissions
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id'               => 'required|exists:students,id',
            'jenis'                    => 'required|in:izin_pulang,izin_keluar',
            'alasan'                   => 'required|string',
            'tanggal_keluar'           => 'required|date',
            'tanggal_kembali_rencana'  => 'required|date|after_or_equal:tanggal_keluar',
            'nama_penjemput'           => 'nullable|string|max:100',
            'hubungan_penjemput'       => 'nullable|string|max:50',
            'kontak_penjemput'         => 'nullable|string|max:30',
        ]);

        $student = Student::findOrFail($data['student_id']);

        $this->authorize('submit', $student);

        abort_unless($student->is_boarding, 422, 'Murid ini bukan santri boarding');

        $data['company_id'] = $student->company_id;
        $data['submitted_by'] = $request->user()->id;
        $data['status'] = 'pending';

        $permission = BoardingPermission::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Pengajuan izin berhasil dikirim',
            'data'    => $permission->load('student:id,name'),
        ], 201);
    }
}
