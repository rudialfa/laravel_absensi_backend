<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leaves;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HrCompanyAnalyticsController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    // =========================================================
    // GET /api/company/hr/analytics/monthly
    // Query: ?month=3&year=2026
    //
    // Laporan bulanan kehadiran seluruh karyawan:
    // - per-hari breakdown hadir/terlambat/alpha/izin/cuti
    // - ringkasan total bulan
    // - top 5 paling terlambat
    // - top 5 paling sering alpha
    // =========================================================
    public function monthly(Request $request)
    {
        $companyId = $this->companyId();
        $month = (int) $request->get('month', Carbon::now()->month);
        $year  = (int) $request->get('year',  Carbon::now()->year);

        if ($month < 1 || $month > 12) {
            return response()->json(['success' => false, 'message' => 'Bulan tidak valid (1-12).'], 422);
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // ── Semua karyawan company ──
        $karyawan = User::where('company_id', $companyId)
            ->where('role', '!=', 'company')
            ->select('id', 'name', 'position', 'department')
            ->get();

        $totalKaryawan = $karyawan->count();

        // ── Semua absensi bulan ini ──
        $attendances = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('user_id', $karyawan->pluck('id'))
            ->get(['user_id', 'date', 'status', 'time_in', 'time_out', 'overtime_minutes']);

        // ── Semua izin bulan ini (approved) ──
        $permissions = Permission::where('company_id', $companyId)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->where('is_approved', true)
            ->get(['user_id', 'date_permission']);

        // ── Semua cuti bulan ini (approved) ──
        $leaves = Leaves::where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['user_id', 'start_date', 'end_date']);

        // ── Ringkasan total bulan ──
        $totalHadir     = $attendances->whereIn('status', ['on_time', 'overtime', 'guest'])->count();
        $totalTerlambat = $attendances->where('status', 'late')->count();
        $totalAlpha     = $attendances->where('status', 'absent')->count();
        $totalIzin      = $permissions->count();
        $totalCuti      = $leaves->count();
        $totalLembur    = $attendances->sum('overtime_minutes');

        // ── Per-hari breakdown ──
        $hariKerja = $start->diffInDays($end) + 1;
        $perHari = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $tgl = $cursor->toDateString();
            $perHari[] = [
                'date'      => $tgl,
                'hari'      => $cursor->locale('id')->isoFormat('dddd'),
                'hadir'     => $attendances->where('date', $tgl)
                    ->whereIn('status', ['on_time', 'overtime', 'guest'])->count(),
                'terlambat' => $attendances->where('date', $tgl)->where('status', 'late')->count(),
                'alpha'     => $attendances->where('date', $tgl)->where('status', 'absent')->count(),
                'izin'      => $permissions->where('date_permission', $tgl)->count(),
            ];
            $cursor->addDay();
        }

        // ── Top 5 terlambat ──
        $top5Terlambat = $attendances->where('status', 'late')
            ->groupBy('user_id')
            ->map(fn($rows, $uid) => [
                'user_id'  => $uid,
                'name'     => $karyawan->firstWhere('id', $uid)?->name,
                'position' => $karyawan->firstWhere('id', $uid)?->position,
                'count'    => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5);

        // ── Top 5 alpha ──
        $top5Alpha = $attendances->where('status', 'absent')
            ->groupBy('user_id')
            ->map(fn($rows, $uid) => [
                'user_id'  => $uid,
                'name'     => $karyawan->firstWhere('id', $uid)?->name,
                'position' => $karyawan->firstWhere('id', $uid)?->position,
                'count'    => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5);

        // ── Label bulan Indonesia ──
        $bulanLabel = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return response()->json([
            'success' => true,
            'message' => 'Laporan analitik bulanan',
            'data'    => [
                'period' => [
                    'month'      => $month,
                    'year'       => $year,
                    'label'      => ($bulanLabel[$month] ?? $month) . ' ' . $year,
                    'hari_kerja' => $hariKerja,
                ],
                'summary' => [
                    'total_karyawan' => $totalKaryawan,
                    'total_hadir'    => $totalHadir,
                    'total_terlambat' => $totalTerlambat,
                    'total_alpha'    => $totalAlpha,
                    'total_izin'     => $totalIzin,
                    'total_cuti'     => $totalCuti,
                    'total_lembur_menit' => $totalLembur,
                ],
                'per_hari'       => $perHari,
                'top_terlambat'  => $top5Terlambat,
                'top_alpha'      => $top5Alpha,
            ],
        ]);
    }

    // =========================================================
    // GET /api/company/hr/analytics/employee/{userId}
    // Query: ?month=3&year=2026
    //
    // Detail laporan per-karyawan: semua absensi + izin + cuti
    // untuk bulan yang dipilih, lengkap per-hari
    // =========================================================
    public function employeeDetail(Request $request, int $userId)
    {
        $companyId = $this->companyId();

        $user = User::where('company_id', $companyId)
            ->select('id', 'name', 'email', 'position', 'department', 'image_url')
            ->findOrFail($userId);

        $month = (int) $request->get('month', Carbon::now()->month);
        $year  = (int) $request->get('year',  Carbon::now()->year);

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get(['date', 'time_in', 'time_out', 'status', 'overtime_minutes']);

        $permissions = Permission::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->get(['date_permission', 'reason', 'is_approved']);

        $leaves = Leaves::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->get(['start_date', 'end_date', 'type', 'reason']);

        $bulanLabel = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return response()->json([
            'success' => true,
            'message' => 'Detail laporan karyawan',
            'data'    => [
                'user'   => $user,
                'period' => [
                    'month' => $month,
                    'year'  => $year,
                    'label' => ($bulanLabel[$month] ?? $month) . ' ' . $year,
                ],
                'summary' => [
                    'hadir'     => $attendances->whereIn('status', ['on_time', 'overtime', 'guest'])->count(),
                    'terlambat' => $attendances->where('status', 'late')->count(),
                    'alpha'     => $attendances->where('status', 'absent')->count(),
                    'izin'      => $permissions->where('is_approved', true)->count(),
                    'cuti'      => $leaves->count(),
                    'total_lembur_menit' => $attendances->sum('overtime_minutes'),
                ],
                'attendances' => $attendances,
                'permissions' => $permissions,
                'leaves'      => $leaves,
            ],
        ]);
    }

    // =========================================================
    // GET /api/company/hr/analytics/attendance-recap
    // Query: ?start=2026-03-01&end=2026-03-31&per_page=15
    //
    // Rekap kehadiran semua karyawan dalam range tanggal
    // Berguna untuk export/tabel di laporan
    // =========================================================
    public function attendanceRecap(Request $request)
    {
        $companyId = $this->companyId();

        $validated = $request->validate([
            'start'    => ['required', 'date'],
            'end'      => ['required', 'date', 'after_or_equal:start'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $start = Carbon::parse($validated['start'])->toDateString();
        $end   = Carbon::parse($validated['end'])->toDateString();

        if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) > 93) {
            return response()->json(['success' => false, 'message' => 'Range maksimal 93 hari.'], 422);
        }

        $data = Attendance::whereBetween('date', [$start, $end])
            ->whereHas('user', fn($q) => $q->where('company_id', $companyId))
            ->with(['user:id,name,position,department'])
            ->orderBy('date', 'desc')
            ->orderBy('time_in', 'desc')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'message' => 'Rekap absensi',
            'data'    => $data,
        ]);
    }
}
