<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\LessonSchedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;

class LessonScheduleController extends Controller
{
    /** Urutan hari Senin → Sabtu (bukan urutan abjad) */
    private const DAY_ORDER = "FIELD(hari, 'senin','selasa','rabu','kamis','jumat','sabtu')";

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
            ->orderByRaw(self::DAY_ORDER)
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
            ->orderByRaw(self::DAY_ORDER)
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

        $this->ensureSameSchool($data, $companyId);
        $this->ensureNoConflict($data);

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
            'jam_selesai'   => 'sometimes|date_format:H:i',
        ]);

        // Gabungkan dengan data lama supaya cek jam & bentrok memakai nilai akhir
        $merged = array_merge([
            'class_id'      => $lessonSchedule->class_id,
            'subject_id'    => $lessonSchedule->subject_id,
            'teacher_id'    => $lessonSchedule->teacher_id,
            'hari'          => $lessonSchedule->hari,
            'jam_mulai'     => $this->hm($lessonSchedule->getRawOriginal('jam_mulai')),
            'jam_selesai'   => $this->hm($lessonSchedule->getRawOriginal('jam_selesai')),
            'academic_year' => $lessonSchedule->academic_year,
        ], $data);

        abort_if($merged['jam_selesai'] <= $merged['jam_mulai'], 422, 'Jam selesai harus setelah jam mulai');

        $this->ensureSameSchool($merged, $lessonSchedule->company_id);
        $this->ensureNoConflict($merged, $lessonSchedule->id);

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

    // ── Helper ────────────────────────────────────────────────

    /** Kelas, mapel & guru harus milik sekolah yang sama */
    private function ensureSameSchool(array $data, int $companyId): void
    {
        abort_unless(ClassRoom::where('id', $data['class_id'])->where('company_id', $companyId)->exists(), 422, 'Kelas bukan milik sekolah ini');
        abort_unless(Subject::where('id', $data['subject_id'])->where('company_id', $companyId)->exists(), 422, 'Mapel bukan milik sekolah ini');
        abort_unless(
            User::where('id', $data['teacher_id'])->where('company_id', $companyId)->whereIn('role', ['guru', 'ustadz'])->exists(),
            422,
            'Guru tidak ditemukan di sekolah ini'
        );
    }

    /**
     * Cek bentrok kelas & guru di hari yang sama.
     * Dua jadwal bentrok kalau: mulai_lama < selesai_baru DAN selesai_lama > mulai_baru.
     * (Jadwal yang bersambung, misal 07:00–08:00 lalu 08:00–09:00, TIDAK dianggap bentrok.)
     */
    private function ensureNoConflict(array $data, ?int $ignoreId = null): void
    {
        $overlap = fn($q) => $q->where('hari', $data['hari'])
            ->where('academic_year', $data['academic_year'])
            ->where('jam_mulai', '<', $data['jam_selesai'])
            ->where('jam_selesai', '>', $data['jam_mulai'])
            ->when($ignoreId, fn($q2) => $q2->where('id', '!=', $ignoreId));

        $classConflict = LessonSchedule::where('class_id', $data['class_id'])->where($overlap)->exists();
        abort_if($classConflict, 422, 'Jadwal kelas ini bentrok dengan jadwal lain di jam tersebut');

        $teacherConflict = LessonSchedule::where('teacher_id', $data['teacher_id'])->where($overlap)->exists();
        abort_if($teacherConflict, 422, 'Guru ini sudah punya jadwal lain di jam tersebut');
    }

    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
