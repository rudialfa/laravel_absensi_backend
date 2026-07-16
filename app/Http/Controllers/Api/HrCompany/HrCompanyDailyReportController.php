<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class HrCompanyDailyReportController extends Controller
{

    // kode 2
    // ─── GET /hr/daily-reports (tidak berubah) ────────────────────────────────
    public function index(Request $request)
    {
        $query = DailyReport::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id);

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
            'message' => 'Berhasil mengambil data daily report',
            'data'    => $query->orderByDesc('date')->paginate($request->get('per_page', 15)),
        ]);
    }

    // ─── GET /hr/daily-reports/{id} (tidak berubah) ───────────────────────────
    public function show($id)
    {
        $report = DailyReport::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $report]);
    }

    // ─── GET /hr/daily-reports/summary (tidak berubah) ────────────────────────
    public function summary(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $summary = DailyReport::where('company_id', Auth::user()->company_id)
            ->whereMonth('date', $request->month)
            ->whereYear('date', $request->year)
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

        return response()->json(['status' => true, 'data' => $summary]);
    }

    // ─── GET /hr/daily-reports/today (tidak berubah) ─────────────────────────
    public function today()
    {
        $today     = now()->toDateString();
        $submitted = DailyReport::with('user:id,name,department,position,image_url')
            ->where('company_id', Auth::user()->company_id)
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

    // ─── GET /hr/daily-reports/employees (BARU) ───────────────────────────────
    // List semua employee + status laporan hari ini
    // Berguna untuk HR lihat siapa yang belum submit
    public function employees(): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        $today     = now()->toDateString();

        $employees = User::where('company_id', $companyId)
            ->where('role', 'employee')
            ->select(['id', 'name', 'position', 'department', 'image_url'])
            ->with(['dailyReports' => function ($q) use ($today) {
                $q->where('date', $today)->latest('id');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                $report = $user->dailyReports->first();
                return [
                    'id'                => (int) $user->id,
                    'name'              => $user->name,
                    'position'          => $user->position,
                    'department'        => $user->department,
                    'image_url'         => $user->image_url,
                    'today_report'      => $report,
                    'submitted_morning' => $report !== null,
                    'submitted_evening' => $report?->achievement !== null,
                    'is_achieved'       => (bool) ($report?->is_achieved ?? false),
                ];
            });

        $total     = $employees->count();
        $submitted = $employees->where('submitted_morning', true)->count();
        $completed = $employees->where('submitted_evening', true)->count();

        return response()->json([
            'status'  => true,
            'message' => 'Status laporan karyawan hari ini',
            'date'    => $today,
            'summary' => [
                'total'         => $total,
                'submitted'     => $submitted,
                'completed'     => $completed,
                'not_submitted' => $total - $submitted,
            ],
            'data' => $employees->values(),
        ]);
    }

    // ─── GET /hr/daily-reports/export (BARU) ─────────────────────────────────
    // Export PDF laporan harian bulanan seluruh karyawan
    //
    // Query params:
    //   month (required)
    //   year  (required)
    //   user_id (opsional — filter 1 karyawan saja)
    //
    // Cara install PDF library:
    //   composer require barryvdh/laravel-dompdf
    //   php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
    // ─────────────────────────────────────────────────────────────────────────
    // Query params:
    //   start_date (required, format Y-m-d)
    //   end_date   (required, format Y-m-d)
    //   user_id    (opsional — filter 1 karyawan saja)
    // ─────────────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'user_id'    => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $companyId = Auth::user()->company_id;
        $startDate = $request->start_date;
        $endDate   = $request->end_date;

        $query = DailyReport::where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with('user:id,name,position,department')
            ->orderBy('date')
            ->orderBy('user_id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        $reports = $query->get();

        // Summary per karyawan
        $summaryPerUser = $reports->groupBy('user_id')->map(function ($items) {
            $total    = $items->count();
            $achieved = $items->where('is_achieved', true)->count();
            $notAchieved = $items->where('is_achieved', false)
                ->whereNotNull('achievement')->count();
            $pending  = $items->whereNull('achievement')->count();
            $rate     = $total > 0
                ? round(($achieved / max($total - $pending, 1)) * 100, 1)
                : 0;

            return [
                'user'             => $items->first()->user,
                'total_days'       => $total,
                'total_achieved'   => $achieved,
                'total_not_achieved' => $notAchieved,
                'total_pending'    => $pending,
                'achievement_rate' => $rate,
                'reports'          => $items,
            ];
        })->values();

        $company     = Auth::user()->company;
        $periodLabel = \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y')
            . ' - ' . \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y');

        $fileName = 'daily-report-' . $startDate . '_to_' . $endDate
            . ($request->filled('user_id') ? '-user' . $request->user_id : '')
            . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_daily_report', [
            'company'        => $company,
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
