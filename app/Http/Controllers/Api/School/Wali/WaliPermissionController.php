<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreStudentPermissionRequest;
use App\Models\Student;
use App\Models\StudentPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WaliPermissionController extends Controller
{
    /**
     * GET /api/school/wali/my-children/{student}/permissions
     */
    public function index(Student $student)
    {
        $this->authorize('view', $student);

        $permissions = $student->permissions()
            ->latest('date_permission')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat izin berhasil diambil',
            'data'    => $permissions,
        ]);
    }

    /**
     * POST /api/school/wali/my-children/{student}/permissions
     */
    public function store(StoreStudentPermissionRequest $request, Student $student)
    {
        $this->authorize('submitPermission', $student);

        $data = $request->validated();
        $data['student_id']   = $student->id;
        $data['submitted_by'] = $request->user()->id;

        $permission = StudentPermission::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Pengajuan izin berhasil dikirim',
            'data'    => $permission,
        ], 201);
    }

    /**
     * POST /api/school/wali/permissions/upload-attachment
     * Upload lampiran izin (foto surat sakit, dll) SEBELUM submit form izin.
     * Return path yang nanti dikirim balik sebagai field 'attachment' saat
     * submit ke endpoint store().
     *
     * Nama file di-random (UUID) supaya tidak bisa ditebak wali lain,
     * dan disimpan per-company supaya gampang dicek kepemilikannya.
     */
    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $companyId = $request->user()->company_id;
        $filename  = Str::uuid() . '.' . $request->file('file')->getClientOriginalExtension();

        $path = $request->file('file')->storeAs(
            "permission-attachments/{$companyId}",
            $filename,
            'public'
        );

        return response()->json([
            'status'  => true,
            'message' => 'Lampiran berhasil diunggah',
            'data'    => [
                'path' => $path,
            ],
        ]);
    }
}
