<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * GET /api/school/admin/subjects
     */
    public function index(Request $request)
    {
        $subjects = Subject::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data mata pelajaran berhasil diambil',
            'data'    => $subjects,
        ]);
    }

    /**
     * POST /api/school/admin/subjects
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'code'             => 'nullable|string|max:20',
            'kelompok'         => 'required|in:wajib,muatan_lokal',
            'grade_level_min'  => 'nullable|integer|min:1|max:6',
            'grade_level_max'  => 'nullable|integer|min:1|max:6|gte:grade_level_min',
        ]);

        $data['company_id'] = $request->user()->company_id;

        $subject = Subject::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Mata pelajaran berhasil dibuat',
            'data'    => $subject,
        ], 201);
    }

    /**
     * GET /api/school/admin/subjects/{subject}
     */
    public function show(Subject $subject)
    {
        $this->authorize('view', $subject);

        return response()->json([
            'status'  => true,
            'message' => 'Detail mata pelajaran berhasil diambil',
            'data'    => $subject,
        ]);
    }

    /**
     * PUT /api/school/admin/subjects/{subject}
     */
    public function update(Request $request, Subject $subject)
    {
        $this->authorize('update', $subject);

        $data = $request->validate([
            'name'             => 'sometimes|string|max:100',
            'code'             => 'nullable|string|max:20',
            'kelompok'         => 'sometimes|in:wajib,muatan_lokal',
            'grade_level_min'  => 'nullable|integer|min:1|max:6',
            'grade_level_max'  => 'nullable|integer|min:1|max:6|gte:grade_level_min',
            'is_active'        => 'sometimes|boolean',
        ]);

        $subject->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Mata pelajaran berhasil diperbarui',
            'data'    => $subject,
        ]);
    }

    /**
     * DELETE /api/school/admin/subjects/{subject}
     */
    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);

        $subject->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Mata pelajaran dihapus',
            'data'    => null,
        ]);
    }
}
