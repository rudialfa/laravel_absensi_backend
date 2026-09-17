<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\StudentAttendance;
use App\Models\StudentPermission;
use Illuminate\Http\Request;

class GuruDashboardController extends Controller
{
    /**
     * GET /api/school/guru/dashboard/summary
     * Ringkasan untuk beranda/profil Guru.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $classIds = $user->teachingClasses()->pluck('class_rooms.id');
        $today = now()->toDateString();

        $totalKelas = $classIds->count();

        $totalMurid = \App\Models\Student::whereIn('class_id', $classIds)->where('is_active', true)->count();

        $attendanceToday = StudentAttendance::whereIn('class_id', $classIds)->where('date', $today)->get();
        $hadir = $attendanceToday->where('status', 'hadir')->count();
        $terlambat = $attendanceToday->where('status', 'terlambat')->count();
        $izin = $attendanceToday->whereIn('status', ['izin', 'sakit'])->count();
        $alpa = $attendanceToday->where('status', 'alpa')->count();

        $pendingPermissions = StudentPermission::whereHas(
            'student',
            fn($q) => $q->whereIn('class_id', $classIds)
        )->where('status', 'pending')->count();

        $upcomingAssignments = Assignment::whereIn('class_id', $classIds)
            ->where('deadline', '>=', now())
            ->orderBy('deadline')
            ->limit(5)
            ->get(['id', 'title', 'deadline']);

        return response()->json([
            'status'  => true,
            'message' => 'Ringkasan dashboard berhasil diambil',
            'data'    => [
                'total_kelas'      => $totalKelas,
                'total_murid'      => $totalMurid,
                'hadir'            => $hadir,
                'terlambat'        => $terlambat,
                'izin'             => $izin,
                'alpa'             => $alpa,
                'izin_pending'     => $pendingPermissions,
                'tugas_mendatang'  => $upcomingAssignments,
            ],
        ]);
    }
}
