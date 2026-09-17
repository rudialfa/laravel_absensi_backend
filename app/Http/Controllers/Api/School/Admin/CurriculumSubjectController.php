<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use Illuminate\Http\Request;

class CurriculumSubjectController extends Controller
{
    /**
     * GET /api/school/admin/curriculums/{curriculum}/subjects?grade_level=
     */
    public function index(Request $request, Curriculum $curriculum)
    {
        $this->authorize('view', $curriculum);

        $items = $curriculum->curriculumSubjects()
            ->with('subject')
            ->when($request->query('grade_level'), fn($q, $g) => $q->where('grade_level', $g))
            ->orderBy('grade_level')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data mapel kurikulum berhasil diambil',
            'data'    => $items,
        ]);
    }

    /**
     * POST /api/school/admin/curriculums/{curriculum}/subjects
     * Tambahkan 1 mapel ke kurikulum untuk 1 tingkat kelas.
     */
    public function attach(Request $request, Curriculum $curriculum)
    {
        $this->authorize('update', $curriculum);

        $data = $request->validate([
            'subject_id'      => 'required|exists:subjects,id',
            'grade_level'     => 'required|integer|min:1|max:6',
            'jam_per_minggu'  => 'required|integer|min:1|max:20',
        ]);

        $exists = CurriculumSubject::where('curricula_id', $curriculum->id)
            ->where('subject_id', $data['subject_id'])
            ->where('grade_level', $data['grade_level'])
            ->exists();

        abort_if($exists, 422, 'Mapel ini sudah terdaftar untuk kelas tersebut di kurikulum ini');

        $data['curricula_id'] = $curriculum->id;

        $item = CurriculumSubject::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Mapel berhasil ditambahkan ke kurikulum',
            'data'    => $item->load('subject'),
        ], 201);
    }

    /**
     * DELETE /api/school/admin/curriculums/{curriculum}/subjects/{curriculumSubject}
     */
    public function detach(Curriculum $curriculum, CurriculumSubject $curriculumSubject)
    {
        $this->authorize('update', $curriculum);

        abort_if($curriculumSubject->curricula_id !== $curriculum->id, 404);

        $curriculumSubject->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Mapel berhasil dihapus dari kurikulum',
            'data'    => null,
        ]);
    }
}
