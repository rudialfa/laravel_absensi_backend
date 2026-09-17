<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\LearningMaterial;
use Illuminate\Http\Request;

class GuruMaterialController extends Controller
{
    /**
     * GET /api/school/guru/materials?class_id=&subject_id=
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'class_id'   => 'required|exists:class_rooms,id',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $data['class_id'])->exists(),
            403,
            'Kelas ini bukan yang Anda ampu'
        );

        $materials = LearningMaterial::where('class_id', $data['class_id'])
            ->when($data['subject_id'] ?? null, fn($q, $id) => $q->where('subject_id', $id))
            ->with(['subject:id,name', 'teacher:id,name'])
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data materi berhasil diambil',
            'data'    => $materials,
        ]);
    }

    /**
     * POST /api/school/guru/materials (multipart)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id'    => 'required|exists:class_rooms,id',
            'subject_id'  => 'required|exists:subjects,id',
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string',
            'file'        => 'nullable|file|max:10240', // max 10MB
        ]);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $data['class_id'])->exists(),
            403,
            'Kelas ini bukan yang Anda ampu'
        );

        $filePath = null;
        $fileName = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('learning-materials/' . $request->user()->company_id, 'public');
        }

        $material = LearningMaterial::create([
            'company_id'  => $request->user()->company_id,
            'class_id'    => $data['class_id'],
            'subject_id'  => $data['subject_id'],
            'teacher_id'  => $request->user()->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path'   => $filePath,
            'file_name'   => $fileName,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Materi berhasil diunggah',
            'data'    => $material->load(['subject:id,name', 'teacher:id,name']),
        ], 201);
    }

    /**
     * DELETE /api/school/guru/materials/{material}
     */
    public function destroy(LearningMaterial $material)
    {
        $this->authorize('delete', $material);

        if ($material->file_path) {
            \Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Materi dihapus',
            'data'    => null,
        ]);
    }
}
