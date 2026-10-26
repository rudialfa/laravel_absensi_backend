<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HrCompanyDailyReportController extends Controller
{
    private const USER_COLS = 'user:id,name,position,department,image_url';

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    private function ensureHr(): void
    {
        if (!Auth::check() || Auth::user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        return (int) Auth::user()->company_id;
    }

    private function validationError($validator)
    {
        return response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422);
    }

    private function attendanceBrief(?Attendance $a): ?array
    {
        if (!$a) return null;

        return [
            'id'           => $a->id,
            'time_in'      => $a->time_in,
            'time_out'     => $a->time_out,
            'status'       => $a->status,
            'late_minutes' => (int) $a->late_minutes,
        ];
    }

    // ============================================================
    // GET /api/company/hr/daily-reports
    // filter: user_id, date, start, end, month, year, is_achieved,
    //         department, pending_evening=1, q, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

        $query = DailyReport::with(self::USER_COLS)->where('company_id', $this->companyId());

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('date'))    $query->whereDate('date', $request->date);
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)->whereYear('date', $request->year);
        }
        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN))
                ->whereNotNull('achievement');
        }
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }
        if ($request->boolean('pending_evening')) {
            $query->whereNull('achievement');
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$kw}%"));
        }

        $reports = $query->orderByDesc('date')->orderBy('user_id')
            ->paginate((int) $request->get('per_page', 15));

        $reports->getCollection()->transform(fn($r) => $r->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data daily report',
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/daily-reports/{id}
    // Detail + absen karyawan di hari yang sama
    // ============================================================
    public function show($id)
    {
        $this->ensureHr();

        $report = DailyReport::with(self::USER_COLS)
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $attendance = Attendance::where('company_id', $this->companyId())
            ->where('user_id', $report->user_id)
            ->whereDate('date', $report->date)
            ->first();

        return response()->json([
            'status'  => true,
            'message' => 'Detail laporan harian',
            'data'    => array_merge($report->toApiArray(), [
                'attendance' => $this->attendanceBrief($attendance),
            ]),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/daily-reports/summary?month=&year=
    // Ringkasan per karyawan
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $rows = DailyReport::where('company_id', $this->companyId())
            ->whereMonth('date', $request->month)
            ->whereYear('date', $request->year)
            ->selectRaw('
                user_id,
                COUNT(*) as total_days,
                SUM(CASE WHEN is_achieved = 1 AND achievement IS NOT NULL THEN 1 ELSE 0 END) as total_achieved,
                SUM(CASE WHEN is_achieved = 0 AND achievement IS NOT NULL THEN 1 ELSE 0 END) as total_not_achieved,
                SUM(CASE WHEN achievement IS NULL THEN 1 ELSE 0 END) as total_pending
            ')
            ->groupBy('user_id')
            ->with(self::USER_COLS)
            ->get()
            ->map(function ($r) {
                $achieved    = (int) $r->total_achieved;
                $notAchieved = (int) $r->total_not_achieved;
                $filled      = $achieved + $notAchieved;

                return [
                    'user_id'            => (int) $r->user_id,
                    'user'               => $r->user ? [
                        'id'         => $r->user->id,
                        'name'       => $r->user->name,
                        'position'   => $r->user->position,
                        'department' => $r->user->department,
                        'image_url'  => $r->user->image_url ? asset($r->user->image_url) : null,
                    ] : null,
                    'total_days'         => (int) $r->total_days,
                    'total_achieved'     => $achieved,
                    'total_not_achieved' => $notAchieved,
                    'total_pending'      => (int) $r->total_pending,
                    'achievement_rate'   => $filled ? round($achieved / $filled * 100, 2) : 0,
                ];
            })
            ->sortByDesc('achievement_rate')
            ->values();

        return response()->json(['status' => true, 'data' => $rows]);
    }

    // ============================================================
    // GET /api/company/hr/daily-reports/today
    // ============================================================
    public function today()
    {
        $this->ensureHr();

        $today     = now()->toDateString();
        $submitted = DailyReport::with(self::USER_COLS)
            ->where('company_id', $this->companyId())
            ->whereDate('date', $today)
            ->get();

        return response()->json([
            'status' => true,
            'date'   => $today,
            'stats'  => [
                'submitted_morning' => $submitted->count(),
                'submitted_evening' => $submitted->whereNotNull('achievement')->count(),
                'pending_evening'   => $submitted->whereNull('achievement')->count(),
            ],
            'data' => $submitted->map(fn($r) => $r->toApiArray())->values(),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/daily-reports/employees?date=
    // Semua karyawan aktif + status laporan di tanggal tsb (default hari ini)
    // ============================================================
    public function employees(Request $request): JsonResponse
    {
        $this->ensureHr();

        $date = $request->filled('date')
            ? Carbon::parse($request->date)->toDateString()
            : now()->toDateString();

        $employees = User::where('company_id', $this->companyId())
            ->where('role', 'employee')
            ->where('is_active', true)
            ->select(['id', 'name', 'position', 'department', 'image_url'])
            ->with(['dailyReports' => fn($q) => $q->whereDate('date', $date)->latest('id')])
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $report = $user->dailyReports->first();

            return [
                    'id'                => (int) $user->id,
                    'name'              => $user->name,
                    'position'          => $user->position,
                    'department'        => $user->department,
                'image_url'         => $user->image_url ? asset($user->image_url) : null,
                'today_report'      => $report?->toApiArray(),
                    'submitted_morning' => $report !== null,
                'submitted_evening' => $report !== null && $report->achievement !== null,
                    'is_achieved'       => (bool) ($report?->is_achieved ?? false),
                ];
            });

        $total     = $employees->count();
        $submitted = $employees->where('submitted_morning', true)->count();
        $completed = $employees->where('submitted_evening', true)->count();

        return response()->json([
            'status'  => true,
            'message' => 'Status laporan karyawan',
            'date'    => $date,
            'summary' => [
                'total'           => $total,
                'submitted'       => $submitted,
                'completed'       => $completed,
                'pending_evening' => $submitted - $completed,
                'not_submitted'   => $total - $submitted,
            ],
            'data' => $employees->values(),
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/daily-reports/export?start_date=&end_date=&user_id=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'user_id'    => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $startDate = Carbon::parse($request->start_date)->toDateString();
        $endDate   = Carbon::parse($request->end_date)->toDateString();

        $query = DailyReport::where('company_id', $this->companyId())
            ->whereBetween('date', [$startDate, $endDate])
            ->with('user:id,name,position,department')
            ->orderBy('date')
            ->orderBy('user_id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        $reports = $query->get();

        $summaryPerUser = $reports->groupBy('user_id')->map(function ($items) {
            $achieved    = $items->where('is_achieved', true)->whereNotNull('achievement')->count();
            $notAchieved = $items->where('is_achieved', false)->whereNotNull('achievement')->count();
            $pending     = $items->whereNull('achievement')->count();
            $filled      = $achieved + $notAchieved;

            return [
                'user'               => $items->first()->user,
                'total_days'         => $items->count(),
                'total_achieved'     => $achieved,
                'total_not_achieved' => $notAchieved,
                'total_pending'      => $pending,
                'achievement_rate'   => $filled ? round($achieved / $filled * 100, 1) : 0,
                'reports'            => $items,
            ];
        })->values();

        $periodLabel = Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' - ' . Carbon::parse($endDate)->translatedFormat('d M Y');

        $fileName = 'daily-report-' . $startDate . '_to_' . $endDate
            . ($request->filled('user_id') ? '-user' . $request->user_id : '')
            . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_daily_report', [
            'company'        => Auth::user()->company,
            'periodLabel'    => $periodLabel,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'summaryPerUser' => $summaryPerUser,
            'totalReports'   => $reports->count(),
            'generatedAt'    => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
