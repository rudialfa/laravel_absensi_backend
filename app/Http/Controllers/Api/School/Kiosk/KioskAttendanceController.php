<?php

namespace App\Http\Controllers\Api\School\Kiosk;

use App\Enums\School\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreKioskAttendanceRequest;
use App\Models\ClassRoom;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KioskAttendanceController extends Controller
{
    /**
     * GET /api/kiosk/classes
     * Daftar kelas di sekolah pada device ini, buat dropdown
     * pemilihan kelas di kiosk (device umum yang tidak terikat 1 kelas).
     */
    public function classes(Request $request)
    {
        $device = $request->attributes->get('attendanceDevice');

        $classes = ClassRoom::where('company_id', $device->company_id)
            ->where('is_active', true)
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get(['id', 'name', 'grade_level']);

        return response()->json([
            'status'  => true,
            'message' => 'Data kelas berhasil diambil',
            'data'    => $classes,
        ]);
    }

    /**
     * GET /api/kiosk/classes/{class}/students
     */
    public function students(Request $request, ClassRoom $class)
    {
        $device = $request->attributes->get('attendanceDevice');

        abort_if(
            $class->company_id !== $device->company_id,
            403,
            'Kelas ini bukan milik sekolah yang sama dengan device'
        );

        $today = now()->toDateString();

        $students = $class->students()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($student) use ($today) {
                $existing = StudentAttendance::where('student_id', $student->id)
                    ->where('date', $today)
                    ->first();

                return [
                    'id'               => $student->id,
                    'name'             => $student->name,
                    'nis'              => $student->nis,
                    'photo_url'        => $student->photo_url,
                    'already_recorded' => (bool) $existing,
                    'current_status'   => $existing?->status,
                ];
            });

        return response()->json([
            'status'  => true,
            'message' => 'Data murid berhasil diambil',
            'data'    => $students,
        ]);
    }

    /**
     * POST /api/kiosk/attendance
     */
    public function store(StoreKioskAttendanceRequest $request)
    {
        $device = $request->attributes->get('attendanceDevice');

        $data = $request->validated();

        abort_if(
            $data['class_id'] != $device->class_id && $device->class_id !== null,
            403,
            'Device ini didedikasikan untuk kelas lain'
        );

        $status = AttendanceStatus::from($data['status']);
        $lateThreshold = config('school.late_threshold', '07:15:00');

        if ($status === AttendanceStatus::Hadir && now()->format('H:i:s') > $lateThreshold) {
            $status = AttendanceStatus::Terlambat;
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $filename = $data['student_id'] . '_' . now()->format('Y-m-d_His') . '.' . $request->file('photo')->extension();
            $photoPath = $request->file('photo')->storeAs('', $filename, 'attendance_photos');
        }

        $existing = StudentAttendance::where('student_id', $data['student_id'])
            ->where('date', now()->toDateString())
            ->first();

        if ($existing && $existing->photo_evidence && $photoPath) {
            Storage::disk('attendance_photos')->delete($existing->photo_evidence);
        }

        $attendance = StudentAttendance::updateOrCreate(
            [
                'student_id' => $data['student_id'],
                'date'       => now()->toDateString(),
            ],
            [
                'company_id'      => $device->company_id,
                'class_id'        => $data['class_id'],
                'status'          => $status,
                'check_in_time'   => now()->toTimeString(),
                'photo_evidence'  => $photoPath ?? $existing?->photo_evidence,
                'device_id'       => $device->id,
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Absen berhasil disimpan',
            'data'    => $attendance,
        ], 201);
    }
}
