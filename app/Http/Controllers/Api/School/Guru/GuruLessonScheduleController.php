<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\LessonSchedule;
use Illuminate\Http\Request;

class GuruLessonScheduleController extends Controller
{
    /**
     * GET /api/school/guru/my-schedule
     * Jadwal mengajar guru ini sendiri, semua kelas, dikelompokkan per hari.
     */
    public function index(Request $request)
    {
        $schedules = LessonSchedule::where('teacher_id', $request->user()->id)
            ->with(['classRoom:id,name', 'subject:id,name'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal mengajar berhasil diambil',
            'data'    => $schedules,
        ]);
    }
}
