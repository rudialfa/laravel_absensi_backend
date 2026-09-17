<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;

class GuruAssignmentController extends Controller
{
    /**
     * GET /api/school/guru/assignments?class_id=&subject_id=
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

        $assignments = Assignment::where('class_id', $data['class_id'])
            ->when($data['subject_id'] ?? null, fn($q, $id) => $q->where('subject_id', $id))
            ->with(['subject:id,name'])
            ->withCount('submissions')
            ->latest('deadline')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data tugas berhasil diambil',
            'data'    => $assignments,
        ]);
    }

    /**
     * POST /api/school/guru/assignments (multipart)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id'    => 'required|exists:class_rooms,id',
            'subject_id'  => 'required|exists:subjects,id',
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string',
            'deadline'    => 'required|date',
            'file'        => 'nullable|file|max:10240',
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
            $filePath = $file->store('assignments/' . $request->user()->company_id, 'public');
        }

        $assignment = Assignment::create([
            'company_id'  => $request->user()->company_id,
            'class_id'    => $data['class_id'],
            'subject_id'  => $data['subject_id'],
            'teacher_id'  => $request->user()->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'deadline'    => $data['deadline'],
            'file_path'   => $filePath,
            'file_name'   => $fileName,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Tugas berhasil dibuat',
            'data'    => $assignment->load('subject:id,name'),
        ], 201);
    }

    /**
     * DELETE /api/school/guru/assignments/{assignment}
     */
    public function destroy(Assignment $assignment)
    {
        $this->authorize('delete', $assignment);

        if ($assignment->file_path) {
            \Storage::disk('public')->delete($assignment->file_path);
        }

        $assignment->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Tugas dihapus',
            'data'    => null,
        ]);
    }

    /**
     * GET /api/school/guru/assignments/{assignment}/submissions
     * Lihat siapa saja yang sudah/belum mengumpulkan.
     */
    public function submissions(Assignment $assignment)
    {
        $this->authorize('view', $assignment);

        $submissions = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->with(['student:id,name', 'submittedBy:id,name'])
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data pengumpulan tugas berhasil diambil',
            'data'    => $submissions,
        ]);
    }

    /**
     * PATCH /api/school/guru/assignments/submissions/{submission}/grade
     */
    public function grade(Request $request, AssignmentSubmission $submission)
    {
        $this->authorize('grade', $submission);

        $data = $request->validate([
            'nilai'         => 'required|numeric|min:0|max:100',
            'catatan_guru'  => 'nullable|string',
        ]);

        $submission->update([
            'nilai'        => $data['nilai'],
            'catatan_guru' => $data['catatan_guru'] ?? null,
            'status'       => 'dikoreksi',
            'graded_by'    => $request->user()->id,
            'graded_at'    => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Tugas berhasil dikoreksi',
            'data'    => $submission->load('student:id,name'),
        ]);
    }
}
