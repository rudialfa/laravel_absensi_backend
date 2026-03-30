<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class SantriDailyReportController extends Controller
{
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Santri)',
            ], 403));
        }
    }

    private function uploadAttachment($file): string
    {
        $destinationPath = public_path('image/daily-reports');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destinationPath, $fileName);
        return 'image/daily-reports/' . $fileName;
    }

    private function deleteAttachment(?string $path): void
    {
        if ($path && File::exists(public_path($path))) {
            File::delete(public_path($path));
        }
    }

    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? (string) $month;
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri/daily-reports
    // Sejajar: EmployeeDailyReportController::index()
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $query = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('date')) $query->where('date', $request->date);
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)->whereYear('date', $request->year);
        }
        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan harian',
            'data'    => $query->orderByDesc('date')->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    // ============================================================
    // TODAY — GET /api/pesantren/santri/daily-reports/today
    // Sejajar: EmployeeDailyReportController::today()
    // ============================================================
    public function today(): JsonResponse
    {
        $this->ensureSantri();

        $today  = now()->toDateString();
        $report = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->where('date', $today)
            ->first();

        return response()->json([
            'status' => true,
            'date'   => $today,
            'data'   => $report,
            'info'   => [
                'submitted_morning' => $report !== null,
                'submitted_evening' => $report?->achievement !== null,
                'is_achieved'       => $report?->is_achieved ?? false,
            ],
        ]);
    }

    // ============================================================
    // SUMMARY — GET /api/pesantren/santri/daily-reports/summary
    // Sejajar: EmployeeDailyReportController::summary()
    // Query: month (required), year (required)
    // ============================================================
    public function summary(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $summary = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->whereMonth('date', $request->month)
            ->whereYear('date',  $request->year)
            ->selectRaw('
                COUNT(*) as total_days,
                SUM(CASE WHEN is_achieved = 1 THEN 1 ELSE 0 END) as total_achieved,
                SUM(CASE WHEN is_achieved = 0 AND achievement IS NOT NULL THEN 1 ELSE 0 END) as total_not_achieved,
                SUM(CASE WHEN achievement IS NULL THEN 1 ELSE 0 END) as total_pending,
                ROUND(
                    SUM(CASE WHEN is_achieved = 1 THEN 1 ELSE 0 END) * 100.0
                    / NULLIF(SUM(CASE WHEN achievement IS NOT NULL THEN 1 ELSE 0 END), 0)
                , 2) as achievement_rate
            ')
            ->first();

        return response()->json([
            'status' => true,
            'data'   => $summary,
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/daily-reports/{id}
    // Sejajar: EmployeeDailyReportController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $report = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $report]);
    }

    // ============================================================
    // STORE — POST /api/pesantren/santri/daily-reports
    // Submit target pagi
    // Sejajar: EmployeeDailyReportController::store()
    // ============================================================
    public function store(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $today = now()->toDateString();

        $exists = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->where('date', $today)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Kamu sudah submit target hari ini',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'target'     => 'required|string',
            'attachment' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = [
            'company_id' => Auth::user()->company_id,
            'user_id'    => Auth::id(),
            'date'       => $today,
            'target'     => $request->target,
        ];

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        $report = DailyReport::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Target pagi berhasil disubmit',
            'data'    => $report,
        ], 201);
    }

    // ============================================================
    // UPDATE — POST /api/pesantren/santri/daily-reports/{id}
    // Update pencapaian sore
    // Sejajar: EmployeeDailyReportController::update()
    // ============================================================
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureSantri();

        $report = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'achievement'         => 'required|string',
            'is_achieved'         => 'required|boolean',
            'reason_not_achieved' => 'nullable|string|required_if:is_achieved,false',
            'attachment'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = [
            'achievement'         => $request->achievement,
            'is_achieved'         => filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN),
            'reason_not_achieved' => $request->reason_not_achieved,
        ];

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($report->attachment);
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        $report->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Pencapaian sore berhasil disubmit',
            'data'    => $report->fresh(),
        ]);
    }

    // ============================================================
    // EXPORT — GET /api/pesantren/santri/daily-reports/export
    // Sejajar: EmployeeDailyReportController::export()
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureSantri();

        $query = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)->whereYear('date', $request->year);
        } elseif ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        } elseif ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN));
        }

        $reports = $query->orderByDesc('date')->get();

        $stats = [
            'total'           => $reports->count(),
            'achieved'        => $reports->where('is_achieved', true)->count(),
            'not_achieved'    => $reports->where('is_achieved', false)->whereNotNull('achievement')->count(),
            'pending_evening' => $reports->whereNull('achievement')->count(),
        ];

        $achievementRate = $stats['achieved'] + $stats['not_achieved'] > 0
            ? round($stats['achieved'] / ($stats['achieved'] + $stats['not_achieved']) * 100, 1)
            : 0;

        $month       = $request->month;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? $this->bulanLabel((int) $month) . ' ' . $year
            : ($request->filled('start') && $request->filled('end')
                ? $request->start . ' s/d ' . $request->end
                : 'Semua Periode');

        $fileName = 'laporan-harian-santri-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.santri_daily_report', [
            'santri'          => Auth::user(),
            'reports'         => $reports,
            'stats'           => $stats,
            'achievementRate' => $achievementRate,
            'periodLabel'     => $periodLabel,
            'generatedAt'     => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
