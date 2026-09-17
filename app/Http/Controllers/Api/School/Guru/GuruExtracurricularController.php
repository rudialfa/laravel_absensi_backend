<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use Illuminate\Http\Request;

class GuruExtracurricularController extends Controller
{
    public function index(Request $request)
    {
        $items = Extracurricular::where('pembina_id', $request->user()->id)
            ->withCount('activeMembers')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data ekstrakurikuler berhasil diambil', 'data' => $items]);
    }

    public function members(Extracurricular $extracurricular)
    {
        abort_unless($extracurricular->pembina_id === request()->user()->id, 403);

        $members = $extracurricular->activeMembers()->with('student:id,name,class_id')->get();

        return response()->json(['status' => true, 'message' => 'Data anggota berhasil diambil', 'data' => $members]);
    }

    public function storeAttendance(Request $request, Extracurricular $extracurricular)
    {
        abort_unless($extracurricular->pembina_id === $request->user()->id, 403);

        $data = $request->validate([
            'tanggal'                => 'required|date',
            'attendance'             => 'required|array|min:1',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status'     => 'required|in:hadir,izin,sakit,alpa',
        ]);

        $saved = [];
        foreach ($data['attendance'] as $item) {
            $saved[] = ExtracurricularAttendance::updateOrCreate(
                [
                    'extracurricular_id' => $extracurricular->id,
                    'student_id'         => $item['student_id'],
                    'tanggal'            => $data['tanggal'],
                ],
                [
                    'status'      => $item['status'],
                    'recorded_by' => $request->user()->id,
                ]
            );
        }

        return response()->json(['status' => true, 'message' => 'Absensi ekskul berhasil disimpan', 'data' => $saved], 201);
    }

    public function getAttendance(Request $request, Extracurricular $extracurricular)
    {
        abort_unless($extracurricular->pembina_id === $request->user()->id, 403);

        $data = $request->validate(['tanggal' => 'required|date']);

        $attendances = ExtracurricularAttendance::where('extracurricular_id', $extracurricular->id)
            ->where('tanggal', $data['tanggal'])
            ->with('student:id,name')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data absensi berhasil diambil', 'data' => $attendances]);
    }
}
