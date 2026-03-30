<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\MutabaahYaumiyah;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PesantrenAnalyticsController extends Controller
{
      // ============================================================
    // PRIVATE HELPERS
    // ============================================================
 
    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }
 
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }
 
    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
            4  => 'April',     5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',      8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',   11 => 'November',  12 => 'Desember',
        ][$month] ?? (string) $month;
    }
 
    // ============================================================
    // MONTHLY — GET /api/pesantren/analytics/monthly
    // Sejajar: HrCompanyAnalyticsController::monthly()
    // Query: month, year
    //
    // Laporan bulanan kehadiran seluruh santri:
    // - per-hari breakdown hadir/terlambat/alpha/izin
    // - ringkasan total bulan
    // - top 5 paling terlambat
    // - top 5 paling sering alpha
    // - summary mutabaah (tambahan pesantren)
    // ============================================================
    public function monthly(Request $request)
    {
        $this->ensureUstadz();
 
        $companyId = $this->companyId();
        $month = (int) $request->get('month', Carbon::now()->month);
        $year  = (int) $request->get('year',  Carbon::now()->year);
 
        if ($month < 1 || $month > 12) {
            return response()->json([
                'status'  => false,
                'message' => 'Bulan tidak valid (1-12).',
            ], 422);
        }
 
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
 
        // ── Semua santri ──
        $santri = User::where('company_id', $companyId)
            ->where('role', 'santri')
            ->select('id', 'name', 'position', 'department')
            ->get();
 
        $totalSantri = $santri->count();
 
        // ── Semua absensi bulan ini ──
        $attendances = Attendance::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('user_id', $santri->pluck('id'))
            ->where('company_id', $companyId)
            ->get(['user_id', 'date', 'status', 'time_in', 'time_out']);
 
        // ── Semua izin bulan ini (approved) ──
        $permissions = Permission::where('company_id', $companyId)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->where('is_approved', true)
            ->whereIn('user_id', $santri->pluck('id'))
            ->get(['user_id', 'date_permission']);
 
        // ── Ringkasan total bulan ──
        $totalHadir     = $attendances->whereIn('status', ['on_time', 'late'])->count();
        $totalTerlambat = $attendances->where('status', 'late')->count();
        $totalAlpha     = $attendances->where('status', 'absent')->count();
        $totalIzin      = $permissions->count();
 
        // ── Per-hari breakdown ──
        $hariDalamBulan = $start->diffInDays($end) + 1;
        $perHari        = [];
        $cursor         = $start->copy();
 
        while ($cursor->lte($end)) {
            $tgl       = $cursor->toDateString();
            $perHari[] = [
                'date'      => $tgl,
                'hari'      => $cursor->locale('id')->isoFormat('dddd'),
                'hadir'     => $attendances->where('date', $tgl)
                    ->whereIn('status', ['on_time', 'late'])->count(),
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
                'user_id'    => $uid,
                'name'       => $santri->firstWhere('id', $uid)?->name,
                'position'   => $santri->firstWhere('id', $uid)?->position,
                'department' => $santri->firstWhere('id', $uid)?->department,
                'count'      => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5);
 
        // ── Top 5 alpha ──
        $top5Alpha = $attendances->where('status', 'absent')
            ->groupBy('user_id')
            ->map(fn($rows, $uid) => [
                'user_id'    => $uid,
                'name'       => $santri->firstWhere('id', $uid)?->name,
                'position'   => $santri->firstWhere('id', $uid)?->position,
                'department' => $santri->firstWhere('id', $uid)?->department,
                'count'      => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(5);
 
        // ── Summary mutabaah bulan ini (tambahan khusus pesantren) ──
        $mutabaahStats = MutabaahYaumiyah::where('company_id', $companyId)
            ->whereMonth('tanggal', $month)
            ->whereYear('tanggal',  $year)
            ->selectRaw('
                COUNT(*)                    as total_sesi,
                COUNT(DISTINCT santri_id)   as total_santri,
                SUM(is_lanjut = 1)          as total_lanjut,
                SUM(is_lanjut = 0)          as total_ulang,
                SUM(signed_by IS NOT NULL)  as total_paraf
            ')
            ->first();
 
        return response()->json([
            'status'  => true,
            'message' => 'Laporan analitik bulanan pesantren',
            'data'    => [
                'period' => [
                    'month'            => $month,
                    'year'             => $year,
                    'label'            => $this->bulanLabel($month) . ' ' . $year,
                    'hari_dalam_bulan' => $hariDalamBulan,
                ],
                'summary' => [
                    'total_santri'    => $totalSantri,
                    'total_hadir'     => $totalHadir,
                    'total_terlambat' => $totalTerlambat,
                    'total_alpha'     => $totalAlpha,
                    'total_izin'      => $totalIzin,
                ],
                'mutabaah' => [
                    'total_sesi'    => (int) ($mutabaahStats->total_sesi   ?? 0),
                    'total_santri'  => (int) ($mutabaahStats->total_santri ?? 0),
                    'total_lanjut'  => (int) ($mutabaahStats->total_lanjut ?? 0),
                    'total_ulang'   => (int) ($mutabaahStats->total_ulang  ?? 0),
                    'total_paraf'   => (int) ($mutabaahStats->total_paraf  ?? 0),
                ],
                'per_hari'      => $perHari,
                'top_terlambat' => $top5Terlambat,
                'top_alpha'     => $top5Alpha,
            ],
        ]);
    }
 
    // ============================================================
    // SANTRI DETAIL — GET /api/pesantren/analytics/santri/{santriId}
    // Sejajar: HrCompanyAnalyticsController::employeeDetail()
    // Query: month, year
    //
    // Detail laporan per-santri: absensi + izin + mutabaah
    // ============================================================
    public function santriDetail(Request $request, int $santriId)
    {
        $this->ensureUstadz();
 
        $companyId = $this->companyId();
 
        $santri = User::where('company_id', $companyId)
            ->where('role', 'santri')
            ->select('id', 'name', 'email', 'position', 'department', 'image_url')
            ->findOrFail($santriId);
 
        $month = (int) $request->get('month', Carbon::now()->month);
        $year  = (int) $request->get('year',  Carbon::now()->year);
 
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
 
        // ── Absensi ──
        $attendances = Attendance::where('user_id', $santriId)
            ->where('company_id', $companyId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get(['date', 'time_in', 'time_out', 'status', 'late_minutes', 'early_leave_minutes', 'face_verified']);
 
        // ── Izin ──
        $permissions = Permission::where('user_id', $santriId)
            ->where('company_id', $companyId)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->get(['date_permission', 'reason', 'is_approved']);
 
        // ── Mutabaah bulan ini ──
        $mutabaah = MutabaahYaumiyah::where('santri_id', $santriId)
            ->where('company_id', $companyId)
            ->whereMonth('tanggal', $month)
            ->whereYear('tanggal',  $year)
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal')
            ->orderBy('sesi')
            ->get();
 
        return response()->json([
            'status'  => true,
            'message' => 'Detail laporan santri',
            'data'    => [
                'santri' => $santri,
                'period' => [
                    'month' => $month,
                    'year'  => $year,
                    'label' => $this->bulanLabel($month) . ' ' . $year,
                ],
                'summary' => [
                    'hadir'               => $attendances->whereIn('status', ['on_time', 'late'])->count(),
                    'terlambat'           => $attendances->where('status', 'late')->count(),
                    'alpha'               => $attendances->where('status', 'absent')->count(),
                    'izin'                => $permissions->where('is_approved', true)->count(),
                    'total_late_minutes'  => (int) $attendances->sum('late_minutes'),
                    'total_early_minutes' => (int) $attendances->sum('early_leave_minutes'),
                ],
                'mutabaah_summary' => [
                    'total_sesi'   => $mutabaah->count(),
                    'total_lanjut' => $mutabaah->where('is_lanjut', true)->count(),
                    'total_ulang'  => $mutabaah->where('is_lanjut', false)->count(),
                    'total_paraf'  => $mutabaah->whereNotNull('signed_by')->count(),
                ],
                'attendances' => $attendances,
                'permissions' => $permissions,
                'mutabaah'    => $mutabaah,
            ],
        ]);
    }
 
    // ============================================================
    // ATTENDANCE RECAP — GET /api/pesantren/analytics/attendance-recap
    // Sejajar: HrCompanyAnalyticsController::attendanceRecap()
    // Query: start (required), end (required), per_page
    //
    // Rekap absensi semua santri dalam range tanggal (max 93 hari)
    // ============================================================
    public function attendanceRecap(Request $request)
    {
        $this->ensureUstadz();
 
        $companyId = $this->companyId();
 
        $validated = $request->validate([
            'start'    => ['required', 'date'],
            'end'      => ['required', 'date', 'after_or_equal:start'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
 
        $start = Carbon::parse($validated['start'])->toDateString();
        $end   = Carbon::parse($validated['end'])->toDateString();
 
        if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) > 93) {
            return response()->json([
                'status'  => false,
                'message' => 'Range maksimal 93 hari.',
            ], 422);
        }
 
        $data = Attendance::whereBetween('date', [$start, $end])
            ->where('company_id', $companyId)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->with(['user:id,name,position,department'])
            ->orderBy('date', 'desc')
            ->orderBy('time_in', 'desc')
            ->paginate((int) ($validated['per_page'] ?? 20));
 
        return response()->json([
            'status'  => true,
            'message' => 'Rekap absensi santri',
            'data'    => $data,
        ]);
    }
}
