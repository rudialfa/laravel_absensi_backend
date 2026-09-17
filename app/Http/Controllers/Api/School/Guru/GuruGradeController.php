<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Http\Request;

class GuruGradeController extends Controller
{
    /**
     * GET /api/school/guru/grades?class_id=&subject_id=&jenis=&semester=&academic_year=
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'class_id'      => 'required|exists:class_rooms,id',
            'subject_id'    => 'required|exists:subjects,id',
            'jenis'         => 'nullable|in:tugas,ujian',
            'semester'      => 'nullable|in:ganjil,genap',
            'academic_year' => 'nullable|string',
        ]);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $data['class_id'])->exists(),
            403,
            'Kelas ini bukan yang Anda ampu'
        );

        $grades = Grade::where('class_id', $data['class_id'])
            ->where('subject_id', $data['subject_id'])
            ->when($data['jenis'] ?? null, fn($q, $j) => $q->where('jenis', $j))
            ->when($data['semester'] ?? null, fn($q, $s) => $q->where('semester', $s))
            ->when($data['academic_year'] ?? null, fn($q, $y) => $q->where('academic_year', $y))
            ->with('student:id,name')
            ->orderByDesc('tanggal')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data nilai berhasil diambil',
            'data'    => $grades,
        ]);
    }

    /**
     * POST /api/school/guru/grades
     * Input nilai 1 murid.
     */
    public function store(Request $request)
    {
        $data = $this->validateGrade($request);

        $student = Student::findOrFail($data['student_id']);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $data['class_id'])->exists(),
            403,
            'Kelas ini bukan yang Anda ampu'
        );

        abort_unless($student->class_id == $data['class_id'], 422, 'Murid ini bukan bagian dari kelas tersebut');

        $data['company_id'] = $request->user()->company_id;
        $data['teacher_id'] = $request->user()->id;

        $grade = Grade::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Nilai berhasil disimpan',
            'data'    => $grade->load('student:id,name'),
        ], 201);
    }

    /**
     * POST /api/school/guru/grades/bulk
     * Input nilai banyak murid sekaligus (1 kelas, 1 mapel, 1 judul tugas/ujian).
     */
    public function storeBulk(Request $request)
    {
        $data = $request->validate([
            'class_id'      => 'required|exists:class_rooms,id',
            'subject_id'    => 'required|exists:subjects,id',
            'jenis'         => 'required|in:tugas,ujian',
            'judul'         => 'required|string|max:150',
            'tanggal'       => 'required|date',
            'semester'      => 'required|in:ganjil,genap',
            'academic_year' => 'required|string',
            'nilai'                 => 'required|array|min:1',
            'nilai.*.student_id'    => 'required|exists:students,id',
            'nilai.*.nilai'         => 'required|numeric|min:0|max:100',
            'nilai.*.catatan'       => 'nullable|string',
        ]);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $data['class_id'])->exists(),
            403,
            'Kelas ini bukan yang Anda ampu'
        );

        $saved = [];

        foreach ($data['nilai'] as $item) {
            $grade = Grade::create([
                'company_id'    => $request->user()->company_id,
                'student_id'    => $item['student_id'],
                'class_id'      => $data['class_id'],
                'subject_id'    => $data['subject_id'],
                'teacher_id'    => $request->user()->id,
                'jenis'         => $data['jenis'],
                'judul'         => $data['judul'],
                'nilai'         => $item['nilai'],
                'tanggal'       => $data['tanggal'],
                'semester'      => $data['semester'],
                'academic_year' => $data['academic_year'],
                'catatan'       => $item['catatan'] ?? null,
            ]);

            $saved[] = $grade->load('student:id,name');
        }

        return response()->json([
            'status'  => true,
            'message' => 'Nilai berhasil disimpan untuk ' . count($saved) . ' murid',
            'data'    => $saved,
        ], 201);
    }

    /**
     * PUT /api/school/guru/grades/{grade}
     */
    public function update(Request $request, Grade $grade)
    {
        $this->authorize('update', $grade);

        $data = $request->validate([
            'judul'   => 'sometimes|string|max:150',
            'nilai'   => 'sometimes|numeric|min:0|max:100',
            'catatan' => 'nullable|string',
        ]);

        $grade->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Nilai berhasil diperbarui',
            'data'    => $grade->load('student:id,name'),
        ]);
    }

    /**
     * DELETE /api/school/guru/grades/{grade}
     */
    public function destroy(Grade $grade)
    {
        $this->authorize('delete', $grade);

        $grade->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Nilai dihapus',
            'data'    => null,
        ]);
    }

    private function validateGrade(Request $request): array
    {
        return $request->validate([
            'student_id'    => 'required|exists:students,id',
            'class_id'      => 'required|exists:class_rooms,id',
            'subject_id'    => 'required|exists:subjects,id',
            'jenis'         => 'required|in:tugas,ujian',
            'judul'         => 'required|string|max:150',
            'nilai'         => 'required|numeric|min:0|max:100',
            'tanggal'       => 'required|date',
            'semester'      => 'required|in:ganjil,genap',
            'academic_year' => 'required|string',
            'catatan'       => 'nullable|string',
        ]);
    }
}
