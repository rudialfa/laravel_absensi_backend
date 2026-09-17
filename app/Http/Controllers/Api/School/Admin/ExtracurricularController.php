<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extracurricular;
use App\Models\ExtracurricularMember;
use App\Models\Student;
use Illuminate\Http\Request;

class ExtracurricularController extends Controller
{
    public function index(Request $request)
    {
        $items = Extracurricular::where('company_id', $request->user()->company_id)
            ->with('pembina:id,name')
            ->withCount('activeMembers')
            ->orderBy('name')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data ekstrakurikuler berhasil diambil', 'data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'description'  => 'nullable|string',
            'pembina_id'   => 'nullable|exists:users,id',
            'hari'         => 'nullable|in:senin,selasa,rabu,kamis,jumat,sabtu',
            'jam_mulai'    => 'nullable|date_format:H:i',
            'jam_selesai'  => 'nullable|date_format:H:i|after:jam_mulai',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $item = Extracurricular::create($data);

        return response()->json(['status' => true, 'message' => 'Ekstrakurikuler berhasil dibuat', 'data' => $item], 201);
    }

    public function show(Extracurricular $extracurricular)
    {
        $this->authorize('view', $extracurricular);
        $extracurricular->load(['pembina:id,name', 'activeMembers.student:id,name,class_id']);

        return response()->json(['status' => true, 'message' => 'Detail ekstrakurikuler berhasil diambil', 'data' => $extracurricular]);
    }

    public function update(Request $request, Extracurricular $extracurricular)
    {
        $this->authorize('manage', $extracurricular);

        $data = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'description'  => 'nullable|string',
            'pembina_id'   => 'nullable|exists:users,id',
            'hari'         => 'nullable|in:senin,selasa,rabu,kamis,jumat,sabtu',
            'jam_mulai'    => 'nullable|date_format:H:i',
            'jam_selesai'  => 'nullable|date_format:H:i|after:jam_mulai',
            'is_active'    => 'sometimes|boolean',
        ]);

        $extracurricular->update($data);

        return response()->json(['status' => true, 'message' => 'Ekstrakurikuler berhasil diperbarui', 'data' => $extracurricular]);
    }

    public function destroy(Extracurricular $extracurricular)
    {
        $this->authorize('manage', $extracurricular);
        $extracurricular->delete();

        return response()->json(['status' => true, 'message' => 'Ekstrakurikuler dihapus', 'data' => null]);
    }

    public function addMember(Request $request, Extracurricular $extracurricular)
    {
        $this->authorize('manage', $extracurricular);

        $data = $request->validate(['student_id' => 'required|exists:students,id']);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->company_id !== $extracurricular->company_id, 403);

        $member = ExtracurricularMember::updateOrCreate(
            ['extracurricular_id' => $extracurricular->id, 'student_id' => $data['student_id']],
            ['joined_at' => now()->toDateString(), 'is_active' => true]
        );

        return response()->json(['status' => true, 'message' => 'Murid berhasil ditambahkan', 'data' => $member->load('student:id,name')], 201);
    }

    public function removeMember(Extracurricular $extracurricular, Student $student)
    {
        $this->authorize('manage', $extracurricular);

        ExtracurricularMember::where('extracurricular_id', $extracurricular->id)
            ->where('student_id', $student->id)
            ->update(['is_active' => false]);

        return response()->json(['status' => true, 'message' => 'Murid dikeluarkan dari ekstrakurikuler', 'data' => null]);
    }
}
