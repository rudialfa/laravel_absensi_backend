<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use Illuminate\Http\Request;

class WaliAssignmentController extends Controller
{
    /**
     * GET /api/school/wali/my-children/{student}/assignments
     * List tugas untuk kelas anak ini, lengkap dengan status pengumpulan
     * anak tersebut (kalau ada).
     */
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $assignments = Assignment::where('class_id', $student->class_id)
            ->with('subject:id,name')
            ->latest('deadline')
            ->get()
            ->map(function ($assignment) use ($student) {
                $submission = AssignmentSubmission::where('assignment_id', $assignment->id)
                    ->where('student_id', $student->id)
                    ->first();

                $assignment->my_submission = $submission;
                return $assignment;
            });

        return response()->json([
            'status'  => true,
            'message' => 'Data tugas berhasil diambil',
            'data'    => $assignments,
        ]);
    }

    /**
     * POST /api/school/wali/assignments/{assignment}/submit (multipart)
     * updateOrCreate — kalau sudah pernah submit, ini jadi resubmit
     * (status balik ke belum_dikoreksi, nilai/catatan lama direset).
     */
    public function submit(Request $request, Assignment $assignment)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'notes'      => 'nullable|string',
            'file'       => 'nullable|file|max:10240',
        ]);

        $student = Student::findOrFail($data['student_id']);

        $this->authorize('view', $student);
        abort_unless($student->class_id === $assignment->class_id, 422, 'Anak ini bukan bagian dari kelas tugas ini');

        $filePath = null;
        $fileName = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->store('assignment-submissions/' . $student->company_id, 'public');
        }

        abort_if(!$filePath && empty($data['notes']), 422, 'Lampirkan file atau isi catatan pengumpulan');

        $submission = AssignmentSubmission::updateOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $student->id],
            [
                'submitted_by' => $request->user()->id,
                'file_path'    => $filePath,
                'file_name'    => $fileName,
                'notes'        => $data['notes'] ?? null,
                'submitted_at' => now(),
                'status'       => 'belum_dikoreksi',
                'nilai'        => null,
                'catatan_guru' => null,
                'graded_by'    => null,
                'graded_at'    => null,
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Tugas berhasil dikumpulkan',
            'data'    => $submission,
        ], 201);
    }
}
