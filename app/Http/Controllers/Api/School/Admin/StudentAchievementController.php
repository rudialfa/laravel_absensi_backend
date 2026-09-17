<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAchievement;
use Illuminate\Http\Request;

class StudentAchievementController extends Controller
{
    public function index(Request $request)
    {
        $items = StudentAchievement::where('company_id', $request->user()->company_id)
            ->with(['student:id,name', 'extracurricular:id,name'])
            ->latest('achieved_at')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data prestasi berhasil diambil', 'data' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'extracurricular_id'  => 'nullable|exists:extracurriculars,id',
            'title'               => 'required|string|max:150',
            'level'               => 'required|in:sekolah,kecamatan,kabupaten,provinsi,nasional,internasional',
            'rank'                => 'nullable|string|max:50',
            'achieved_at'         => 'required|date',
            'certificate'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->company_id !== $request->user()->company_id, 403);

        $certPath = null;
        if ($request->hasFile('certificate')) {
            $certPath = $request->file('certificate')->store('achievements/' . $student->company_id, 'public');
        }

        $achievement = StudentAchievement::create([
            'company_id'          => $student->company_id,
            'student_id'          => $data['student_id'],
            'extracurricular_id'  => $data['extracurricular_id'] ?? null,
            'title'               => $data['title'],
            'level'               => $data['level'],
            'rank'                => $data['rank'] ?? null,
            'achieved_at'         => $data['achieved_at'],
            'certificate_path'    => $certPath,
            'recorded_by'         => $request->user()->id,
        ]);

        return response()->json(['status' => true, 'message' => 'Prestasi berhasil dicatat', 'data' => $achievement->load('student:id,name')], 201);
    }

    public function destroy(StudentAchievement $achievement)
    {
        abort_if($achievement->company_id !== request()->user()->company_id, 403);

        if ($achievement->certificate_path) {
            \Storage::disk('public')->delete($achievement->certificate_path);
        }

        $achievement->delete();

        return response()->json(['status' => true, 'message' => 'Prestasi dihapus', 'data' => null]);
    }
}
