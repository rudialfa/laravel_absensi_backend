<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Leaves;
use App\Models\Permission;
use Carbon\Carbon;


class HrCompanyDashboardController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    // =========================================================
    // GET /api/company/hr/dashboard/summary
    //
    // Response: {
    //   "success": true,
    //   "data": {
    //     "today": "2026-03-01",
    //     "total_karyawan":     { "count": 248 },
    //     "hadir_hari_ini":     { "count": 231 },
    //     "terlambat_hari_ini": { "count": 5 },
    //     "izin_pending":       { "count": 3 },
    //     "cuti_pending":       { "count": 2 },
    //     "absensi_terbaru":    [ { user, position, time_in, status } ... ],
    //     "izin_pending_list":  [ { id, user, date, reason } ... ],
    //   }
    // }
    // =========================================================
    public function summary()
    {
        $companyId = $this->companyId();
        $today     = Carbon::now()->toDateString();

        // ── Total karyawan aktif perusahaan ──
        $totalKaryawan = User::where('company_id', $companyId)
            ->where('role', '!=', 'company')
            ->count();

        // ── Absensi hari ini ──
        $attendancesToday = Attendance::where('date', $today)
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->get();

        $hadirHariIni     = $attendancesToday->whereIn('status', ['on_time', 'overtime', 'guest'])->count();
        $terlambatHariIni = $attendancesToday->where('status', 'late')->count();

        // ── Izin pending (is_approved = null) ──
        $izinPending = Permission::where('company_id', $companyId)
            ->whereNull('is_approved')
            ->count();

        // ── Cuti pending ──
        $cutiPending = Leaves::where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        // ── Absensi terbaru (10 terakhir hari ini) ──
        $absensiTerbaru = Attendance::where('date', $today)
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->with(['user:id,name,position,department'])
            ->orderBy('time_in', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id'         => $a->id,
                'name'       => $a->user?->name,
                'position'   => $a->user?->position,
                'department' => $a->user?->department,
                'time_in'    => $a->time_in,
                'time_out'   => $a->time_out,
                'status'     => $a->status,
            ]);

        // ── Izin pending list (5 terbaru) ──
        $izinPendingList = Permission::where('company_id', $companyId)
            ->whereNull('is_approved')
            ->with(['user:id,name,position,department'])
            ->orderBy('date_permission', 'asc')
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'id'      => $p->id,
                'name'    => $p->user?->name,
                'position' => $p->user?->position,
                'date'    => $p->date_permission,
                'reason'  => $p->reason,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Summary dashboard HR',
            'data'    => [
                'today'              => $today,
                'total_karyawan'     => ['count' => $totalKaryawan,     'label' => 'Total Karyawan'],
                'hadir_hari_ini'     => ['count' => $hadirHariIni,      'label' => 'Hadir Hari Ini'],
                'terlambat_hari_ini' => ['count' => $terlambatHariIni,  'label' => 'Terlambat'],
                'izin_pending'       => ['count' => $izinPending,       'label' => 'Izin Pending'],
                'cuti_pending'       => ['count' => $cutiPending,       'label' => 'Cuti Pending'],
                'absensi_terbaru'    => $absensiTerbaru,
                'izin_pending_list'  => $izinPendingList,
            ],
        ]);
    }
}
