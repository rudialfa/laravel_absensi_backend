<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\StudentBill;
use Illuminate\Http\Request;

class WaliDashboardController extends Controller
{
    /**
     * GET /api/school/wali/dashboard/summary
     * Ringkasan untuk beranda/profil Wali — per anak.
     */
    public function summary(Request $request)
    {
        $user = $request->user();
        $children = $user->guardedStudents()->get(['students.id', 'students.name']);

        $totalAnak = $children->count();

        $currentMonth = now()->month;
        $currentYear = now()->year;

        $hadirBulanIni = StudentAttendance::whereIn('student_id', $children->pluck('id'))
            ->whereMonth('date', $currentMonth)
            ->whereYear('date', $currentYear)
            ->whereIn('status', ['hadir', 'terlambat'])
            ->count();

        $izinPending = \App\Models\StudentPermission::whereIn('student_id', $children->pluck('id'))
            ->where('status', 'pending')
            ->count();

        $totalTagihanBelumLunas = StudentBill::whereIn('student_id', $children->pluck('id'))
            ->where('status', '!=', 'lunas')
            ->get()
            ->sum(fn($b) => $b->remaining_amount);

        return response()->json([
            'status'  => true,
            'message' => 'Ringkasan dashboard berhasil diambil',
            'data'    => [
                'total_anak'                    => $totalAnak,
                'hadir_bulan_ini'                => $hadirBulanIni,
                'izin_pending'                   => $izinPending,
                'total_tagihan_belum_lunas'      => $totalTagihanBelumLunas,
                'periode_label'                   => now()->translatedFormat('F Y'),
            ],
        ]);
    }
}
