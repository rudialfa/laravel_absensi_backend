<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;

class WaliGradeController extends Controller
{
    /**
     * GET /api/school/wali/my-children/{student}/grades?semester=&academic_year=
     */
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $data = $request->validate([
            'semester'      => 'nullable|in:ganjil,genap',
            'academic_year' => 'nullable|string',
        ]);

        $grades = Grade::where('student_id', $student->id)
            ->when($data['semester'] ?? null, fn($q, $s) => $q->where('semester', $s))
            ->when($data['academic_year'] ?? null, fn($q, $y) => $q->where('academic_year', $y))
            ->with('subject:id,name')
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data nilai anak berhasil diambil',
            'data'    => $grades,
        ]);
    }
}
