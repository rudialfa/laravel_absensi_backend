<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentMutation;
use Illuminate\Http\Request;

class StudentMutationController extends Controller
{
    /**
     * GET /api/school/admin/student-mutations?type=&student_id=
     */
    public function index(Request $request)
    {
        $mutations = StudentMutation::where('company_id', $request->user()->company_id)
            ->with(['student:id,name', 'processedBy:id,name'])
            ->when($request->query('type'), fn($q, $t) => $q->where('type', $t))
            ->when($request->query('student_id'), fn($q, $id) => $q->where('student_id', $id))
            ->latest('effective_date')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data mutasi siswa berhasil diambil',
            'data'    => $mutations,
        ]);
    }

    /**
     * POST /api/school/admin/student-mutations/masuk (multipart)
     * Catat riwayat pendidikan siswa yang sudah dibuat lewat
     * StudentController biasa — dipakai buat dokumentasi asal sekolah,
     * TIDAK membuat siswa baru (siswa harus sudah ada dulu).
     */
    public function recordMasuk(Request $request)
    {
        $data = $request->validate([
            'student_id'     => 'required|exists:students,id',
            'origin_school'  => 'required|string|max:150',
            'reason'         => 'nullable|string',
            'effective_date' => 'required|date',
            'letter_number'  => 'nullable|string|max:100',
            'document'       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->company_id !== $request->user()->company_id, 403);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('mutations/' . $student->company_id, 'public');
        }

        $mutation = StudentMutation::create([
            'company_id'     => $student->company_id,
            'student_id'     => $student->id,
            'type'           => 'masuk',
            'origin_school'  => $data['origin_school'],
            'reason'         => $data['reason'] ?? null,
            'effective_date' => $data['effective_date'],
            'letter_number'  => $data['letter_number'] ?? null,
            'document_path'  => $documentPath,
            'processed_by'   => $request->user()->id,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat mutasi masuk berhasil dicatat',
            'data'    => $mutation->load('student:id,name'),
        ], 201);
    }

    /**
     * POST /api/school/admin/student-mutations/keluar (multipart)
     * Catat mutasi keluar — sekaligus nonaktifkan siswa dari sistem
     * (is_active = false, class_id = null), sama seperti alur "lulus"
     * di ClassPromotionController.
     */
    public function recordKeluar(Request $request)
    {
        $data = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'destination_school'  => 'required|string|max:150',
            'reason'              => 'nullable|string',
            'effective_date'      => 'required|date',
            'letter_number'       => 'nullable|string|max:100',
            'document'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->company_id !== $request->user()->company_id, 403);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('mutations/' . $student->company_id, 'public');
        }

        $mutation = StudentMutation::create([
            'company_id'          => $student->company_id,
            'student_id'          => $student->id,
            'type'                => 'keluar',
            'destination_school'  => $data['destination_school'],
            'reason'              => $data['reason'] ?? null,
            'effective_date'      => $data['effective_date'],
            'letter_number'       => $data['letter_number'] ?? null,
            'document_path'       => $documentPath,
            'processed_by'        => $request->user()->id,
        ]);

        $student->update(['is_active' => false, 'class_id' => null]);

        return response()->json([
            'status'  => true,
            'message' => 'Mutasi keluar berhasil dicatat, siswa dinonaktifkan',
            'data'    => $mutation->load('student:id,name'),
        ], 201);
    }
}
