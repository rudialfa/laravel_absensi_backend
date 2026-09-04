<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;

class GuruAttendanceController extends Controller
{
    /**
     * GET /api/school/guru/my-classes/{class}/attendance?date=YYYY-MM-DD
     * List absen 1 kelas untuk 1 tanggal (default: hari ini).
     */
    public function index(Request $request, ClassRoom $class)
    {
        $this->authorize('view', $class);

        $data = $request->validate(['date' => 'nullable|date']);
        $date = $data['date'] ?? now()->toDateString();

        $attendances = StudentAttendance::where('class_id', $class->id)
            ->where('date', $date)
            ->with('student:id,name,photo_url')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data absensi berhasil diambil',
            'data'    => $attendances,
        ]);
    }

    /**
     * POST /api/school/guru/my-classes/{class}/attendance
     * Simpan absen manual (batch) dari dashboard guru.
     */
    public function store(Request $request, ClassRoom $class)
    {
        $this->authorize('recordAttendance', $class);

        $data = $request->validate([
            'date'                  => 'required|date',
            'records'               => 'required|array|min:1',
            'records.*.student_id'  => 'required|exists:students,id',
            'records.*.status'      => 'required|in:hadir,terlambat,izin,sakit,alpa',
            'records.*.notes'       => 'nullable|string',
        ]);

        $saved = [];

        foreach ($data['records'] as $record) {
            $attendance = StudentAttendance::updateOrCreate(
                ['student_id' => $record['student_id'], 'date' => $data['date']],
                [
                    'company_id'  => $class->company_id,
                    'class_id'    => $class->id,
                    'status'      => $record['status'],
                    'notes'       => $record['notes'] ?? null,
                    'recorded_by' => $request->user()->id,
                ]
            );

            $saved[] = $attendance->load('student:id,name,photo_url');
        }

        return response()->json([
            'status'  => true,
            'message' => 'Absen berhasil disimpan',
            'data'    => $saved,
        ]);
    }

    /**
     * PATCH /api/school/guru/attendance/{attendance}
     * Koreksi 1 baris absen (misal salah pencet status).
     */
    public function update(Request $request, StudentAttendance $attendance)
    {
        $this->authorize('recordAttendance', $attendance->classRoom);

        $data = $request->validate([
            'status' => 'required|in:hadir,terlambat,izin,sakit,alpa',
            'notes'  => 'nullable|string',
        ]);

        $attendance->update([...$data, 'recorded_by' => $request->user()->id]);

        return response()->json([
            'status'  => true,
            'message' => 'Absen berhasil diperbarui',
            'data'    => $attendance->load('student:id,name,photo_url'),
        ]);
    }
}
