<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PesantrenMonthlyReportController extends Controller
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
    // INDEX — GET /api/pesantren/monthly-reports
    // Sejajar: HrCompanyMonthlyReportController::index()
    // Query: user_id, month, year, status, department (kelas), per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureUstadz();

        $query = MonthlyReport::with([
            'user:id,name,position,department,image_url',
            'approver:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'));

        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('month'))      $query->where('month', $request->month);
        if ($request->filled('year'))       $query->where('year',  $request->year);
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan santri',
            'data'    => $query->orderByDesc('year')
                ->orderByDesc('month')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/monthly-reports/{id}
    // Sejajar: HrCompanyMonthlyReportController::show()
    // ============================================================
    public function show(int $id)
    {
        $this->ensureUstadz();

        $report = MonthlyReport::with([
            'user:id,name,position,department,image_url',
            'approver:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $report,
        ]);
    }

    // ============================================================
    // APPROVE — PATCH /api/pesantren/monthly-reports/{id}/approve
    // Sejajar: HrCompanyMonthlyReportController::approve()
    // ============================================================
    public function approve(Request $request, int $id)
    {
        $this->ensureUstadz();

        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);

        if ($report->status !== 'submitted') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus submitted yang bisa diapprove',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'score' => 'required|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $report->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'score'       => $request->score,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan santri berhasil diapprove',
            'data'    => $report->fresh(['user:id,name', 'approver:id,name']),
        ]);
    }

    // ============================================================
    // REJECT — PATCH /api/pesantren/monthly-reports/{id}/reject
    // Sejajar: HrCompanyMonthlyReportController::reject()
    // ============================================================
    public function reject(int $id)
    {
        $this->ensureUstadz();

        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);

        if ($report->status !== 'submitted') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus submitted yang bisa ditolak',
            ], 422);
        }

        $report->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan santri ditolak',
            'data'    => $report->fresh(['user:id,name', 'approver:id,name']),
        ]);
    }

    // ============================================================
    // SANTRI REPORTS — GET /api/pesantren/monthly-reports/santri
    // List laporan bulanan semua santri (tambahan khusus pesantren)
    // ============================================================
    public function santriReports(Request $request)
    {
        $this->ensureUstadz();

        $month = (int) $request->get('month', now()->month);
        $year  = (int) $request->get('year',  now()->year);

        $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('month', $month)
            ->where('year',  $year)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->with(['user:id,name,position,department,image_url', 'approver:id,name'])
            ->orderByDesc('score')
            ->get();

        $stats = [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
        ];

        return response()->json([
            'status'  => true,
            'message' => 'Laporan bulanan santri',
            'period'  => [
                'month' => $month,
                'year'  => $year,
                'label' => $this->bulanLabel($month) . ' ' . $year,
            ],
            'stats'   => $stats,
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // SUMMARY — GET /api/pesantren/monthly-reports/summary
    // Sejajar: HrCompanyMonthlyReportController::summary()
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

        $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('month', $request->month)
            ->where('year',  $request->year)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->with('user:id,name,department,position,image_url')
            ->get();

        $stats = [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
        ];

        return response()->json([
            'status' => true,
            'stats'  => $stats,
            'data'   => $reports,
        ]);
    }

    // ============================================================
    // EXPORT — GET /api/pesantren/monthly-reports/export
    // Sejajar: HrCompanyMonthlyReportController::export()
    // Query: month (required), year (required), status (opsional)
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();

        $validator = Validator::make($request->all(), [
            'month'  => 'required|integer|between:1,12',
            'year'   => 'required|integer|min:2020|max:2099',
            'status' => 'nullable|in:draft,submitted,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $month  = (int) $request->month;
        $year   = (int) $request->year;
        $status = $request->status ?? null;

        $query = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('month', $month)
            ->where('year',  $year)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->with(['user:id,name,position,department', 'approver:id,name'])
            ->orderBy('user_id');

        if ($status) $query->where('status', $status);

        $reports = $query->get();

        $stats = [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
        ];

        $periodLabel = $this->bulanLabel($month) . ' ' . $year;
        $statusLabel = $status ? ' - ' . ucfirst($status) : '';
        $fileName    = 'laporan-bulanan-santri-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_monthly_report', [
            'company'     => Auth::user()->company ?? (object)['name' => ''],
            'periodLabel' => $periodLabel . $statusLabel,
            'reports'     => $reports,
            'stats'       => $stats,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
