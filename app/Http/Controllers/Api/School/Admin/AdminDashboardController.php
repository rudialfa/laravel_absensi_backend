<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\ClassRoom;
use App\Models\StudentAttendance;
use App\Models\StudentBill;
use App\Models\StudentPermission;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/school/admin/dashboard/summary
     * Ringkasan untuk beranda/profil Admin.
     */
    public function summary(Request $request)
    {
        $companyId = $request->user()->company_id;
        $today = now()->toDateString();

        $totalGuru = User::where('company_id', $companyId)->where('role', 'guru')->count();
        $totalWali = User::where('company_id', $companyId)->where('role', 'wali')->count();
        $totalKelas = ClassRoom::where('company_id', $companyId)->where('is_active', true)->count();
        $totalMurid = \App\Models\Student::where('company_id', $companyId)->where('is_active', true)->count();

        $attendanceToday = StudentAttendance::where('company_id', $companyId)->where('date', $today)->get();
        $hadirToday = $attendanceToday->whereIn('status', ['hadir', 'terlambat'])->count();
        $attendancePercentage = $totalMurid > 0 ? round(($hadirToday / $totalMurid) * 100, 1) : 0;

        // FIX: student_permissions tidak punya kolom company_id sendiri —
        // company_id ada di tabel students. Filter lewat relasi student()
        // pakai whereHas, bukan where('company_id', ...) langsung.
        $pendingPermissions = StudentPermission::where('status', 'pending')
            ->whereHas('student', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->count();

        $unpaidBillsTotal = StudentBill::where('company_id', $companyId)
            ->where('status', '!=', 'lunas')
            ->get()
            ->sum(fn($b) => $b->remaining_amount);

        $upcomingEvents = AcademicEvent::where('company_id', $companyId)
            ->where('tanggal_mulai', '>=', $today)
            ->orderBy('tanggal_mulai')
            ->limit(5)
            ->get(['id', 'title', 'jenis', 'tanggal_mulai']);

        // TODO: modul Pengumuman (Announcement) belum dibuat — nanti aktifkan
        // lagi query ini setelah model & tabel `announcements` tersedia.
        $recentAnnouncements = [];

        return response()->json([
            'status'  => true,
            'message' => 'Ringkasan dashboard berhasil diambil',
            'data'    => [
                'total_guru'                => $totalGuru,
                'total_wali'                 => $totalWali,
                'total_kelas'                => $totalKelas,
                'total_murid'                => $totalMurid,
                'kehadiran_hari_ini_persen'  => $attendancePercentage,
                'izin_pending'               => $pendingPermissions,
                'total_piutang'              => $unpaidBillsTotal,
                'event_mendatang'            => $upcomingEvents,
                'pengumuman_terbaru'         => $recentAnnouncements,
            ],
        ]);
    }
}
