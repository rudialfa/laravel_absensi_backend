<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use App\Models\Student;
use Illuminate\Http\Request;

class WaliMaterialController extends Controller
{
    /**
     * GET /api/school/wali/my-children/{student}/materials?subject_id=
     */
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $materials = LearningMaterial::where('class_id', $student->class_id)
            ->when($request->query('subject_id'), fn($q, $id) => $q->where('subject_id', $id))
            ->with(['subject:id,name', 'teacher:id,name'])
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data materi berhasil diambil',
            'data'    => $materials,
        ]);
    }
}
