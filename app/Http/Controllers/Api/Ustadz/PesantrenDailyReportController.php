<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class PesantrenDailyReportController extends Controller
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
    // INDEX — GET /api/pesantren/daily-reports
    // Sejajar: HrCompanyDailyReportController::index()
    // Query: user_id, date, start, end, month, year,
    //        is_achieved, department (kelas), pending_evening, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureUstadz();
 
        $query = DailyReport::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'));
 
        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('date'))       $query->where('date', $request->date);
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)->whereYear('date', $request->year);
        }
        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }
        if ($request->filled('pending_evening') && $request->pending_evening) {
            $query->whereNull('achievement');
        }
 
        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan harian santri',
            'data'    => $query->orderByDesc('date')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }
 
    // ============================================================
    // SHOW — GET /api/pesantren/daily-reports/{id}
    // Sejajar: HrCompanyDailyReportController::show()
    // ============================================================
    public function show(int $id)
    {
        $this->ensureUstadz();
 
        $report = DailyReport::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);
 
        return response()->json([
            'status' => true,
            'data'   => $report,
        ]);
    }
 
    // ============================================================
    // SUMMARY — GET /api/pesantren/daily-reports/summary
    // Sejajar: HrCompanyDailyReportController::summary()
    // Query: month (required), year (required)
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureUstadz();
 
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
 
        $summary = DailyReport::where('company_id', Auth::user()->company_id)
            ->whereMonth('date', $request->month)
            ->whereYear('date',  $request->year)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->selectRaw('
                user_id,
                COUNT(*) as total_days,
                SUM(CASE WHEN is_achieved = 1 THEN 1 ELSE 0 END) as total_achieved,
                SUM(CASE WHEN is_achieved = 0 AND achievement IS NOT NULL THEN 1 ELSE 0 END) as total_not_achieved,
                SUM(CASE WHEN achievement IS NULL THEN 1 ELSE 0 END) as total_pending,
                ROUND(
                    SUM(CASE WHEN is_achieved = 1 THEN 1 ELSE 0 END) * 100.0
                    / NULLIF(SUM(CASE WHEN achievement IS NOT NULL THEN 1 ELSE 0 END), 0)
                , 2) as achievement_rate
            ')
            ->groupBy('user_id')
            ->with('user:id,name,department,position,image_url')
            ->get();
 
        return response()->json([
            'status' => true,
            'data'   => $summary,
        ]);
    }
 
    // ============================================================
    // TODAY — GET /api/pesantren/daily-reports/today
    // Sejajar: HrCompanyDailyReportController::today()
    // ============================================================
    public function today()
    {
        $this->ensureUstadz();
 
        $today     = now()->toDateString();
        $submitted = DailyReport::with('user:id,name,department,position,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->where('date', $today)
            ->get();
 
        return response()->json([
            'status' => true,
            'date'   => $today,
            'stats'  => [
                'submitted_morning' => $submitted->count(),
                'submitted_evening' => $submitted->whereNotNull('achievement')->count(),
                'pending_evening'   => $submitted->whereNull('achievement')->count(),
            ],
            'data' => $submitted,
        ]);
    }
 
    // ============================================================
    // SANTRI REPORTS — GET /api/pesantren/daily-reports/santri
    // List semua santri + status laporan hari ini
    // Sejajar: HrCompanyDailyReportController::employees()
    // ============================================================
    public function santriReports(): JsonResponse
    {
        $this->ensureUstadz();
 
        $companyId = Auth::user()->company_id;
        $today     = now()->toDateString();
 
        $santriList = User::where('company_id', $companyId)
            ->where('role', 'santri')
            ->select(['id', 'name', 'position', 'department', 'image_url'])
            ->with(['dailyReports' => function ($q) use ($today) {
                $q->where('date', $today)->latest('id');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($santri) {
                $report = $santri->dailyReports->first();
                return [
                    'id'                => (int) $santri->id,
                    'name'              => $santri->name,
                    'position'          => $santri->position,
                    'department'        => $santri->department,
                    'image_url'         => $santri->image_url,
                    'today_report'      => $report,
                    'submitted_morning' => $report !== null,
                    'submitted_evening' => $report?->achievement !== null,
                    'is_achieved'       => (bool) ($report?->is_achieved ?? false),
                ];
            });
 
        $total     = $santriList->count();
        $submitted = $santriList->where('submitted_morning', true)->count();
        $completed = $santriList->where('submitted_evening', true)->count();
 
        return response()->json([
            'status'  => true,
            'message' => 'Status laporan santri hari ini',
            'date'    => $today,
            'summary' => [
                'total'         => $total,
                'submitted'     => $submitted,
                'completed'     => $completed,
                'not_submitted' => $total - $submitted,
            ],
            'data' => $santriList->values(),
        ]);
    }
 
    // ============================================================
    // EXPORT — GET /api/pesantren/daily-reports/export
    // Sejajar: HrCompanyDailyReportController::export()
    // Query: month (required), year (required), user_id (opsional)
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();
 
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
 
        $companyId = Auth::user()->company_id;
        $month     = (int) $request->month;
        $year      = (int) $request->year;
 
        $query = DailyReport::where('company_id', $companyId)
            ->whereMonth('date', $month)
            ->whereYear('date',  $year)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->with('user:id,name,position,department')
            ->orderBy('date')
            ->orderBy('user_id');
 
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }
 
        $reports = $query->get();
 
        $summaryPerSantri = $reports->groupBy('user_id')->map(function ($items) {
            $total       = $items->count();
            $achieved    = $items->where('is_achieved', true)->count();
            $notAchieved = $items->where('is_achieved', false)->whereNotNull('achievement')->count();
            $pending     = $items->whereNull('achievement')->count();
            $rate        = $total > 0
                ? round(($achieved / max($total - $pending, 1)) * 100, 1)
                : 0;
 
            return [
                'santri'             => $items->first()->user,
                'total_days'         => $total,
                'total_achieved'     => $achieved,
                'total_not_achieved' => $notAchieved,
                'total_pending'      => $pending,
                'achievement_rate'   => $rate,
                'reports'            => $items,
            ];
        })->values();
 
        $periodLabel = $this->bulanLabel($month) . ' ' . $year;
        $fileName    = 'laporan-harian-santri-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';
 
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_daily_report', [
            'company'          => Auth::user()->company,
            'periodLabel'      => $periodLabel,
            'month'            => $month,
            'year'             => $year,
            'summaryPerSantri' => $summaryPerSantri,
            'totalReports'     => $reports->count(),
            'generatedAt'      => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);
 
        return $pdf->download($fileName);
    }
}
