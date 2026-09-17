<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;

class AdminGradeController extends Controller
{
    /**
     * GET /api/school/admin/grades-report?class_id=&subject_id=&semester=&academic_year=
     * Rekap rata-rata nilai per murid, per mapel.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'class_id'      => 'required|exists:class_rooms,id',
            'subject_id'    => 'nullable|exists:subjects,id',
            'semester'      => 'required|in:ganjil,genap',
            'academic_year' => 'required|string',
        ]);

        $grades = Grade::where('class_id', $data['class_id'])
            ->where('semester', $data['semester'])
            ->where('academic_year', $data['academic_year'])
            ->when($data['subject_id'] ?? null, fn($q, $id) => $q->where('subject_id', $id))
            ->with(['student:id,name', 'subject:id,name'])
            ->get();

        // Kelompokkan & rata-ratakan per murid+mapel+jenis
        $grouped = $grades->groupBy(fn($g) => $g->student_id . '-' . $g->subject_id)
            ->map(function ($items) {
                $first = $items->first();
                $tugas = $items->where('jenis', 'tugas');
                $ujian = $items->where('jenis', 'ujian');

                return [
                    'student_id'      => $first->student_id,
                    'student_name'    => $first->student->name,
                    'subject_id'      => $first->subject_id,
                    'subject_name'    => $first->subject->name,
                    'rata_tugas'      => round($tugas->avg('nilai') ?? 0, 2),
                    'rata_ujian'      => round($ujian->avg('nilai') ?? 0, 2),
                    'rata_keseluruhan' => round($items->avg('nilai'), 2),
                    'total_penilaian' => $items->count(),
                ];
            })
            ->values();

        return response()->json([
            'status'  => true,
            'message' => 'Rekap nilai berhasil diambil',
            'data'    => $grouped,
        ]);
    }
}
