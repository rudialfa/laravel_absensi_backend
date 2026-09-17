<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\LessonSchedule;
use Illuminate\Http\Request;

class LessonScheduleController extends Controller
{
    /**
     * GET /api/school/admin/lesson-schedules?class_id=&hari=&academic_year=
     */
    public function index(Request $request)
    {
        $schedules = LessonSchedule::where('company_id', $request->user()->company_id)
            ->with(['classRoom:id,name', 'subject:id,name', 'teacher:id,name'])
            ->when($request->query('class_id'), fn($q, $id) => $q->where('class_id', $id))
            ->when($request->query('hari'), fn($q, $h) => $q->where('hari', $h))
            ->when($request->query('academic_year'), fn($q, $y) => $q->where('academic_year', $y))
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    /**
     * GET /api/school/admin/classes/{class}/lesson-schedules
     * Jadwal 1 kelas, dikelompokkan per hari — cocok buat tampilan
     * "jadwal mingguan" 1 kelas.
     */
    public function byClass(ClassRoom $class)
    {
        $this->authorize('view', $class);

        $schedules = LessonSchedule::where('class_id', $class->id)
            ->with(['subject:id,name', 'teacher:id,name'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal kelas berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    /**
     * POST /api/school/admin/lesson-schedules
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'class_id'      => 'required|exists:class_rooms,id',
            'subject_id'    => 'required|exists:subjects,id',
            'teacher_id'    => 'required|exists:users,id',
            'hari'          => 'required|in:senin,selasa,rabu,kamis,jumat,sabtu',
            'jam_mulai'     => 'required|date_format:H:i',
            'jam_selesai'   => 'required|date_format:H:i|after:jam_mulai',
            'academic_year' => 'required|string|max:20',
        ]);

        $companyId = $request->user()->company_id;

        // Cek bentrok: kelas yang sama tidak boleh punya 2 jadwal yang
        // waktunya overlap di hari yang sama.
        $classConflict = LessonSchedule::where('class_id', $data['class_id'])
            ->where('hari', $data['hari'])
            ->where('academic_year', $data['academic_year'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('jam_mulai', [$data['jam_mulai'], $data['jam_selesai']])
                    ->orWhereBetween('jam_selesai', [$data['jam_mulai'], $data['jam_selesai']])
                    ->orWhere(function ($q2) use ($data) {
                        $q2->where('jam_mulai', '<=', $data['jam_mulai'])
                            ->where('jam_selesai', '>=', $data['jam_selesai']);
                    });
            })
            ->exists();

        abort_if($classConflict, 422, 'Jadwal kelas ini bentrok dengan jadwal lain di jam tersebut');

        // Cek bentrok guru — guru yang sama tidak boleh ngajar 2 kelas
        // bersamaan di hari & jam yang overlap.
        $teacherConflict = LessonSchedule::where('teacher_id', $data['teacher_id'])
            ->where('hari', $data['hari'])
            ->where('academic_year', $data['academic_year'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('jam_mulai', [$data['jam_mulai'], $data['jam_selesai']])
                    ->orWhereBetween('jam_selesai', [$data['jam_mulai'], $data['jam_selesai']])
                    ->orWhere(function ($q2) use ($data) {
                        $q2->where('jam_mulai', '<=', $data['jam_mulai'])
                            ->where('jam_selesai', '>=', $data['jam_selesai']);
                    });
            })
            ->exists();

        abort_if($teacherConflict, 422, 'Guru ini sudah punya jadwal lain di jam tersebut');

        $data['company_id'] = $companyId;

        $schedule = LessonSchedule::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal berhasil dibuat',
            'data'    => $schedule->load(['classRoom:id,name', 'subject:id,name', 'teacher:id,name']),
        ], 201);
    }

    /**
     * PUT /api/school/admin/lesson-schedules/{lessonSchedule}
     */
    public function update(Request $request, LessonSchedule $lessonSchedule)
    {
        $this->authorize('update', $lessonSchedule);

        $data = $request->validate([
            'class_id'      => 'sometimes|exists:class_rooms,id',
            'subject_id'    => 'sometimes|exists:subjects,id',
            'teacher_id'    => 'sometimes|exists:users,id',
            'hari'          => 'sometimes|in:senin,selasa,rabu,kamis,jumat,sabtu',
            'jam_mulai'     => 'sometimes|date_format:H:i',
            'jam_selesai'   => 'sometimes|date_format:H:i|after:jam_mulai',
        ]);

        $lessonSchedule->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal berhasil diperbarui',
            'data'    => $lessonSchedule->load(['classRoom:id,name', 'subject:id,name', 'teacher:id,name']),
        ]);
    }

    /**
     * DELETE /api/school/admin/lesson-schedules/{lessonSchedule}
     */
    public function destroy(LessonSchedule $lessonSchedule)
    {
        $this->authorize('delete', $lessonSchedule);

        $lessonSchedule->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal dihapus',
            'data'    => null,
        ]);
    }
}
