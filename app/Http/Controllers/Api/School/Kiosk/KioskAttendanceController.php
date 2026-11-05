<?php

namespace App\Http\Controllers\Api\School\Kiosk;

use App\Enums\School\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreKioskAttendanceRequest;
use App\Models\ClassRoom;
use App\Models\Student;
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
            // Kiosk khusus 1 kelas hanya menampilkan kelasnya sendiri
            ->when($device->class_id, fn($q) => $q->where('id', $device->class_id))
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
            (int) $class->company_id !== (int) $device->company_id,
            403,
            'Kelas ini bukan milik sekolah yang sama dengan device'
        );

        $today = now()->toDateString();

        $students = $class->students()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Ambil absen hari ini sekaligus (bukan 1 query per murid)
        $todayAttendances = StudentAttendance::whereIn('student_id', $students->pluck('id'))
            ->where('date', $today)
            ->get()
            ->keyBy('student_id');

        $students = $students->map(function ($student) use ($todayAttendances) {
            $existing = $todayAttendances->get($student->id);

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
            $device->class_id !== null && (int) $data['class_id'] !== (int) $device->class_id,
            403,
            'Device ini didedikasikan untuk kelas lain'
        );

        // Kelas & murid wajib milik sekolah yang sama dengan device (cegah absen lintas tenant)
        $class = ClassRoom::where('id', $data['class_id'])
            ->where('company_id', $device->company_id)
            ->first();

        abort_if(!$class, 403, 'Kelas ini bukan milik sekolah yang sama dengan device');

        $student = Student::where('id', $data['student_id'])
            ->where('company_id', $device->company_id)
            ->where('is_active', true)
            ->first();

        abort_if(!$student, 403, 'Murid tidak ditemukan di sekolah ini');
        abort_if((int) $student->class_id !== (int) $class->id, 422, 'Murid ini tidak terdaftar di kelas yang dipilih');

        $status = AttendanceStatus::from($data['status']);
        $lateThreshold = config('school.late_threshold', '07:15:00');

        if ($status === AttendanceStatus::Hadir && now()->format('H:i:s') > $lateThreshold) {
            $status = AttendanceStatus::Terlambat;
        }

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $filename = $student->id . '_' . now()->format('Y-m-d_His') . '.' . $request->file('photo')->extension();
            $photoPath = $request->file('photo')->storeAs('', $filename, 'attendance_photos');
        }

        $existing = StudentAttendance::where('student_id', $student->id)
            ->where('date', now()->toDateString())
            ->first();

        if ($existing && $existing->photo_evidence && $photoPath) {
            Storage::disk('attendance_photos')->delete($existing->photo_evidence);
        }

        $attendance = StudentAttendance::updateOrCreate(
            [
                'student_id' => $student->id,
                'date'       => now()->toDateString(),
            ],
            [
                'company_id'      => $device->company_id,
                'class_id'        => $class->id,
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