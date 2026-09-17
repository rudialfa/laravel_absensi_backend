<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\HealthRecord;
use App\Models\Student;
use App\Models\UksVisit;
use Illuminate\Http\Request;

class GuruUksController extends Controller
{
    public function healthRecord(Request $request, Student $student)
    {
        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $student->class_id)->exists(),
            403,
            'Murid ini bukan bagian dari kelas Anda'
        );

        $record = HealthRecord::where('student_id', $student->id)->first();

        return response()->json(['status' => true, 'message' => 'Data kesehatan berhasil diambil', 'data' => $record]);
    }

    public function listVisits(Request $request)
    {
        $classIds = $request->user()->teachingClasses()->pluck('class_rooms.id');

        $visits = UksVisit::whereHas('student', fn($q) => $q->whereIn('class_id', $classIds))
            ->with('student:id,name,class_id')
            ->latest('visited_at')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data kunjungan UKS berhasil diambil', 'data' => $visits]);
    }

    public function recordVisit(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'complaint'  => 'required|string',
            'treatment'  => 'nullable|string',
            'outcome'    => 'required|in:kembali_kelas,pulang,dirujuk',
            'notes'      => 'nullable|string',
        ]);

        $student = Student::findOrFail($data['student_id']);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $student->class_id)->exists(),
            403,
            'Murid ini bukan bagian dari kelas Anda'
        );

        $visit = UksVisit::create([
            'company_id'  => $student->company_id,
            'student_id'  => $data['student_id'],
            'complaint'   => $data['complaint'],
            'treatment'   => $data['treatment'] ?? null,
            'outcome'     => $data['outcome'],
            'notes'       => $data['notes'] ?? null,
            'visited_at'  => now(),
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json(['status' => true, 'message' => 'Kunjungan UKS berhasil dicatat', 'data' => $visit->load('student:id,name')], 201);
    }
}
