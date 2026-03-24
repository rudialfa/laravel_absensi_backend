<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HrCompanyMonthlyReportController extends Controller
{
    // // ─── GET /hr/monthly-reports ──────────────────────────────────────────────
    // // Lihat semua laporan bulanan semua karyawan
    // public function index(Request $request)
    // {
    //     $query = MonthlyReport::with([
    //         'user:id,name,position,department,image_url',
    //         'approver:id,name',
    //     ])
    //         ->where('company_id', Auth::user()->company_id);

    //     // Filter per karyawan
    //     if ($request->filled('user_id')) {
    //         $query->where('user_id', $request->user_id);
    //     }

    //     // Filter per bulan
    //     if ($request->filled('month')) {
    //         $query->where('month', $request->month);
    //     }

    //     // Filter per tahun
    //     if ($request->filled('year')) {
    //         $query->where('year', $request->year);
    //     }

    //     // Filter status: draft | submitted | approved | rejected
    //     if ($request->filled('status')) {
    //         $query->where('status', $request->status);
    //     }

    //     // Filter per departemen
    //     if ($request->filled('department')) {
    //         $query->whereHas('user', fn($q) => $q->where('department', $request->department));
    //     }

    //     $reports = $query->orderByDesc('year')
    //         ->orderByDesc('month')
    //         ->paginate($request->get('per_page', 15));

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Berhasil mengambil data laporan bulanan',
    //         'data'    => $reports,
    //     ]);
    // }

    // // ─── GET /hr/monthly-reports/{id} ─────────────────────────────────────────
    // // Detail satu laporan
    // public function show($id)
    // {
    //     $report = MonthlyReport::with([
    //         'user:id,name,position,department,image_url',
    //         'approver:id,name',
    //     ])
    //         ->where('company_id', Auth::user()->company_id)
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status' => true,
    //         'data'   => $report,
    //     ]);
    // }

    // // ─── PATCH /hr/monthly-reports/{id}/approve ───────────────────────────────
    // // Approve laporan + beri skor
    // public function approve(Request $request, $id)
    // {
    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->findOrFail($id);

    //     if ($report->status !== 'submitted') {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Hanya laporan berstatus submitted yang bisa diapprove',
    //         ], 422);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'score' => 'required|numeric|min:0|max:100',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $report->update([
    //         'status'      => 'approved',
    //         'approved_by' => Auth::id(),
    //         'approved_at' => now(),
    //         'score'       => $request->score,
    //     ]);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil diapprove',
    //         'data'    => $report->fresh(['user:id,name', 'approver:id,name']),
    //     ]);
    // }

    // // ─── PATCH /hr/monthly-reports/{id}/reject ────────────────────────────────
    // // Reject laporan
    // public function reject($id)
    // {
    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->findOrFail($id);

    //     if ($report->status !== 'submitted') {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Hanya laporan berstatus submitted yang bisa ditolak',
    //         ], 422);
    //     }

    //     $report->update([
    //         'status'      => 'rejected',
    //         'approved_by' => Auth::id(),
    //         'approved_at' => now(),
    //     ]);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan ditolak',
    //         'data'    => $report->fresh(['user:id,name', 'approver:id,name']),
    //     ]);
    // }

    // // ─── GET /hr/monthly-reports/summary ──────────────────────────────────────
    // // Rekap laporan bulanan per bulan: total, approved, rejected, pending, avg score
    // public function summary(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'month' => 'required|integer|between:1,12',
    //         'year'  => 'required|integer',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('month', $request->month)
    //         ->where('year', $request->year)
    //         ->with('user:id,name,department,position,image_url')
    //         ->get();

    //     $stats = [
    //         'total'     => $reports->count(),
    //         'approved'  => $reports->where('status', 'approved')->count(),
    //         'rejected'  => $reports->where('status', 'rejected')->count(),
    //         'submitted' => $reports->where('status', 'submitted')->count(),
    //         'draft'     => $reports->where('status', 'draft')->count(),
    //         'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
    //     ];

    //     return response()->json([
    //         'status' => true,
    //         'stats'  => $stats,
    //         'data'   => $reports,
    //     ]);
    // }

    // kode 2
    // ============================================================
    // GET /api/company/hr/monthly-reports
    // ============================================================
    public function index(Request $request)
    {
        $query = MonthlyReport::with([
            'user:id,name,position,department,image_url',
            'approver:id,name',
        ])->where('company_id', Auth::user()->company_id);

        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('month'))      $query->where('month', $request->month);
        if ($request->filled('year'))       $query->where('year', $request->year);
        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }

        $reports = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan',
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/monthly-reports/{id}
    // ============================================================
    public function show($id)
    {
        $report = MonthlyReport::with([
            'user:id,name,position,department,image_url',
            'approver:id,name',
        ])
            ->where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $report,
        ]);
    }

    // ============================================================
    // PATCH /api/company/hr/monthly-reports/{id}/approve
    // ============================================================
    public function approve(Request $request, $id)
    {
        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
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
            'message' => 'Laporan berhasil diapprove',
            'data'    => $report->fresh(['user:id,name', 'approver:id,name']),
        ]);
    }

    // ============================================================
    // PATCH /api/company/hr/monthly-reports/{id}/reject
    // ============================================================
    public function reject($id)
    {
        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
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
            'message' => 'Laporan ditolak',
            'data'    => $report->fresh(['user:id,name', 'approver:id,name']),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/monthly-reports/summary
    // ============================================================
    public function summary(Request $request)
    {
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
            ->where('year', $request->year)
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
    // EXPORT PDF — rekap laporan bulanan per periode
    // GET /api/company/hr/monthly-reports/export
    //
    // Query params:
    //   month  (required) — 1-12
    //   year   (required)
    //   status (optional) — draft|submitted|approved|rejected
    //
    // Install: composer require barryvdh/laravel-dompdf
    // ============================================================
    public function export(Request $request)
    {
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
            ->where('year', $year)
            ->with(['user:id,name,position,department', 'approver:id,name'])
            ->orderBy('user_id');

        if ($status) $query->where('status', $status);

        $reports = $query->get();

        // Summary
        $stats = [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
        ];

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

        $periodLabel = ($bulanLabel[$month] ?? $month) . ' ' . $year;
        $statusLabel = $status ? ' - ' . ucfirst($status) : '';
        $fileName    = 'monthly-report-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_monthly_report', [
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
