<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\HealthRecord;
use App\Models\Student;
use App\Models\UksVisit;
use Illuminate\Http\Request;

class AdminUksController extends Controller
{
    public function listHealthRecords(Request $request)
    {
        $records = HealthRecord::where('company_id', $request->user()->company_id)
            ->with('student:id,name,class_id')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data kesehatan berhasil diambil', 'data' => $records]);
    }

    public function saveHealthRecord(Request $request)
    {
        $data = $request->validate([
            'student_id'               => 'required|exists:students,id',
            'blood_type'                => 'nullable|string|max:5',
            'height_cm'                 => 'nullable|numeric',
            'weight_kg'                 => 'nullable|numeric',
            'allergies'                 => 'nullable|string',
            'chronic_conditions'        => 'nullable|string',
            'emergency_contact_name'    => 'nullable|string|max:100',
            'emergency_contact_phone'   => 'nullable|string|max:30',
        ]);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->company_id !== $request->user()->company_id, 403);

        $record = HealthRecord::updateOrCreate(
            ['student_id' => $data['student_id']],
            [
                'company_id'   => $student->company_id,
                'blood_type'   => $data['blood_type'] ?? null,
                'height_cm'    => $data['height_cm'] ?? null,
                'weight_kg'    => $data['weight_kg'] ?? null,
                'allergies'    => $data['allergies'] ?? null,
                'chronic_conditions' => $data['chronic_conditions'] ?? null,
                'emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'recorded_at'  => now()->toDateString(),
                'recorded_by'  => $request->user()->id,
            ]
        );

        return response()->json(['status' => true, 'message' => 'Data kesehatan berhasil disimpan', 'data' => $record], 201);
    }

    public function listVisits(Request $request)
    {
        $visits = UksVisit::where('company_id', $request->user()->company_id)
            ->with('student:id,name,class_id')
            ->latest('visited_at')
            ->paginate(30);

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
        abort_if($student->company_id !== $request->user()->company_id, 403);

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
