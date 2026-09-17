<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAchievement;
use Illuminate\Http\Request;

class WaliExtracurricularController extends Controller
{
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $memberships = $student->extracurricularMemberships()
            ->where('is_active', true)
            ->with('extracurricular:id,name')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data ekstrakurikuler anak berhasil diambil', 'data' => $memberships]);
    }

    public function achievements(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $achievements = StudentAchievement::where('student_id', $student->id)
            ->with('extracurricular:id,name')
            ->latest('achieved_at')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data prestasi anak berhasil diambil', 'data' => $achievements]);
    }
}
