<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurriculumController extends Controller
{
    /**
     * GET /api/school/admin/curriculums
     */
    public function index(Request $request)
    {
        $curriculums = Curriculum::where('company_id', $request->user()->company_id)
            ->withCount('curriculumSubjects')
            ->orderByDesc('academic_year')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data kurikulum berhasil diambil',
            'data'    => $curriculums,
        ]);
    }

    /**
     * POST /api/school/admin/curriculums
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'academic_year' => 'required|string|max:20',
            'description'   => 'nullable|string',
            'is_active'     => 'boolean',
        ]);

        $data['company_id'] = $request->user()->company_id;

        // Kalau kurikulum baru ini diset aktif, nonaktifkan kurikulum lain
        // di tahun ajaran yang sama — supaya tidak ada 2 kurikulum aktif
        // bareng untuk 1 tahun ajaran (bikin ambigu pas dipakai di Jadwal).
        if ($data['is_active'] ?? true) {
            Curriculum::where('company_id', $data['company_id'])
                ->where('academic_year', $data['academic_year'])
                ->update(['is_active' => false]);
        }

        $curriculum = Curriculum::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Kurikulum berhasil dibuat',
            'data'    => $curriculum,
        ], 201);
    }

    /**
     * GET /api/school/admin/curriculums/{curriculum}
     */
    public function show(Curriculum $curriculum)
    {
        $this->authorize('view', $curriculum);

        $curriculum->load('curriculumSubjects.subject');

        return response()->json([
            'status'  => true,
            'message' => 'Detail kurikulum berhasil diambil',
            'data'    => $curriculum,
        ]);
    }

    /**
     * PUT /api/school/admin/curriculums/{curriculum}
     */
    public function update(Request $request, Curriculum $curriculum)
    {
        $this->authorize('update', $curriculum);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:150',
            'academic_year' => 'sometimes|string|max:20',
            'description'   => 'nullable|string',
            'is_active'     => 'sometimes|boolean',
        ]);

        if (($data['is_active'] ?? false) === true) {
            Curriculum::where('company_id', $curriculum->company_id)
                ->where('academic_year', $data['academic_year'] ?? $curriculum->academic_year)
                ->where('id', '!=', $curriculum->id)
                ->update(['is_active' => false]);
        }

        $curriculum->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Kurikulum berhasil diperbarui',
            'data'    => $curriculum,
        ]);
    }

    /**
     * DELETE /api/school/admin/curriculums/{curriculum}
     */
    public function destroy(Curriculum $curriculum)
    {
        $this->authorize('delete', $curriculum);

        $curriculum->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Kurikulum dihapus',
            'data'    => null,
        ]);
    }
}
