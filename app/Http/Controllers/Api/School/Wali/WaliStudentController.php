<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class WaliStudentController extends Controller
{
    /**
     * GET /api/school/wali/my-children
     * Semua anak yang terhubung ke akun wali ini.
     */
    public function index(Request $request)
    {
        $children = $request->user()->guardedStudents()
            ->with('classRoom:id,name')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data anak berhasil diambil',
            'data'    => $children,
        ]);
    }

    /**
     * GET /api/school/wali/my-children/{student}
     */
    public function show(Student $student)
    {
        $this->authorize('view', $student);

        return response()->json([
            'status'  => true,
            'message' => 'Detail anak berhasil diambil',
            'data'    => $student->load('classRoom:id,name'),
        ]);
    }
}
