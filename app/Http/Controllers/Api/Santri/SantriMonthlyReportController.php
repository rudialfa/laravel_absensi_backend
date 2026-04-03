<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class SantriMonthlyReportController extends Controller
{
    // // ============================================================
    // // PRIVATE HELPERS
    // // ============================================================

    // private function ensureSantri(): void
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'santri') {
    //         abort(response()->json([
    //             'status'  => false,
    //             'message' => 'Akses ditolak (khusus Santri)',
    //         ], 403));
    //     }
    // }

    // private function uploadAttachment($file): string
    // {
    //     $destinationPath = public_path('image/monthly-reports');
    //     if (!File::exists($destinationPath)) {
    //         File::makeDirectory($destinationPath, 0755, true);
    //     }
    //     $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
    //     $file->move($destinationPath, $fileName);
    //     return 'image/monthly-reports/' . $fileName;
    // }

    // private function deleteAttachment(?string $path): void
    // {
    //     if ($path && File::exists(public_path($path))) {
    //         File::delete(public_path($path));
    //     }
    // }

    // private function bulanLabel(int $month): string
    // {
    //     return [
    //         1  => 'Januari',
    //         2  => 'Februari',
    //         3  => 'Maret',
    //         4  => 'April',
    //         5  => 'Mei',
    //         6  => 'Juni',
    //         7  => 'Juli',
    //         8  => 'Agustus',
    //         9  => 'September',
    //         10 => 'Oktober',
    //         11 => 'November',
    //         12 => 'Desember',
    //     ][$month] ?? (string) $month;
    // }

    // // ============================================================
    // // INDEX — GET /api/pesantren/santri/monthly-reports
    // // Sejajar: EmployeeMonthlyReportController::index()
    // // ============================================================
    // public function index(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $query = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id());

    //     if ($request->filled('month'))  $query->where('month',  $request->month);
    //     if ($request->filled('year'))   $query->where('year',   $request->year);
    //     if ($request->filled('status')) $query->where('status', $request->status);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Berhasil mengambil data laporan bulanan',
    //         'data'    => $query->orderByDesc('year')
    //             ->orderByDesc('month')
    //             ->paginate((int) $request->get('per_page', 15)),
    //     ]);
    // }

    // // ============================================================
    // // SUMMARY — GET /api/pesantren/santri/monthly-reports/summary
    // // Sejajar: EmployeeMonthlyReportController::summary()
    // // ============================================================
    // public function summary(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->when($request->filled('year'), fn($q) => $q->where('year', $request->year))
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'data'   => [
    //             'total'     => $reports->count(),
    //             'approved'  => $reports->where('status', 'approved')->count(),
    //             'rejected'  => $reports->where('status', 'rejected')->count(),
    //             'submitted' => $reports->where('status', 'submitted')->count(),
    //             'draft'     => $reports->where('status', 'draft')->count(),
    //             'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
    //         ],
    //     ]);
    // }

    // // ============================================================
    // // SHOW — GET /api/pesantren/santri/monthly-reports/{id}
    // // Sejajar: EmployeeMonthlyReportController::show()
    // // ============================================================
    // public function show(int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $report = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     return response()->json(['status' => true, 'data' => $report]);
    // }

    // // ============================================================
    // // STORE — POST /api/pesantren/santri/monthly-reports
    // // Buat draft laporan bulanan
    // // Sejajar: EmployeeMonthlyReportController::store()
    // // ============================================================
    // public function store(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $validator = Validator::make($request->all(), [
    //         'month'       => 'required|integer|between:1,12',
    //         'year'        => 'required|integer',
    //         'target'      => 'required|string',
    //         'achievement' => 'required|string',
    //         'problem'     => 'required|string',
    //         'solution'    => 'required|string',
    //         'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $exists = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->where('month', $request->month)
    //         ->where('year',  $request->year)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Laporan bulan ' . $request->month . '/' . $request->year . ' sudah ada',
    //         ], 422);
    //     }

    //     $data = [
    //         'company_id'  => Auth::user()->company_id,
    //         'user_id'     => Auth::id(),
    //         'month'       => $request->month,
    //         'year'        => $request->year,
    //         'target'      => $request->target,
    //         'achievement' => $request->achievement,
    //         'problem'     => $request->problem,
    //         'solution'    => $request->solution,
    //         'status'      => 'draft',
    //     ];

    //     if ($request->hasFile('attachment')) {
    //         $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
    //     }

    //     $report = MonthlyReport::create($data);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil dibuat sebagai draft',
    //         'data'    => $report,
    //     ], 201);
    // }

    // // ============================================================
    // // UPDATE — POST /api/pesantren/santri/monthly-reports/{id}
    // // Edit draft / laporan yang ditolak
    // // Sejajar: EmployeeMonthlyReportController::update()
    // // ============================================================
    // public function update(Request $request, int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     if (!in_array($report->status, ['draft', 'rejected'])) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Laporan yang sudah disubmit / diapprove tidak bisa diedit',
    //         ], 422);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'target'      => 'sometimes|string',
    //         'achievement' => 'sometimes|string',
    //         'problem'     => 'sometimes|string',
    //         'solution'    => 'sometimes|string',
    //         'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
    //     }

    //     $data = array_filter([
    //         'target'      => $request->target,
    //         'achievement' => $request->achievement,
    //         'problem'     => $request->problem,
    //         'solution'    => $request->solution,
    //     ]);

    //     if ($request->hasFile('attachment')) {
    //         $this->deleteAttachment($report->attachment);
    //         $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
    //     }

    //     // Jika sebelumnya rejected, reset ke draft
    //     if ($report->status === 'rejected') {
    //         $data['status']      = 'draft';
    //         $data['approved_by'] = null;
    //         $data['approved_at'] = null;
    //         $data['score']       = 0;
    //     }

    //     $report->update($data);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil diupdate',
    //         'data'    => $report->fresh('approver:id,name'),
    //     ]);
    // }

    // // ============================================================
    // // SUBMIT — PATCH /api/pesantren/santri/monthly-reports/{id}/submit
    // // Submit draft ke ustadz
    // // Sejajar: EmployeeMonthlyReportController::submit()
    // // ============================================================
    // public function submit(int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     if ($report->status !== 'draft') {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Hanya laporan berstatus draft yang bisa disubmit',
    //         ], 422);
    //     }

    //     $report->update(['status' => 'submitted']);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Laporan berhasil disubmit ke ustadz',
    //         'data'    => $report->fresh(),
    //     ]);
    // }

    // // ============================================================
    // // DESTROY — DELETE /api/pesantren/santri/monthly-reports/{id}
    // // Sejajar: EmployeeMonthlyReportController::destroy()
    // // ============================================================
    // public function destroy(int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $report = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     if ($report->status !== 'draft') {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Hanya laporan berstatus draft yang bisa dihapus',
    //         ], 422);
    //     }

    //     $this->deleteAttachment($report->attachment);
    //     $report->delete();

    //     return response()->json(['status' => true, 'message' => 'Laporan berhasil dihapus']);
    // }

    // // ============================================================
    // // EXPORT — GET /api/pesantren/santri/monthly-reports/export
    // // Sejajar: EmployeeMonthlyReportController::export()
    // // ============================================================
    // public function export(Request $request)
    // {
    //     $this->ensureSantri();

    //     $query = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->orderByDesc('year')
    //         ->orderByDesc('month');

    //     if ($request->filled('year'))   $query->where('year',   $request->year);
    //     if ($request->filled('month'))  $query->where('month',  $request->month);
    //     if ($request->filled('status')) $query->where('status', $request->status);

    //     $reports = $query->get();

    //     $stats = [
    //         'total'     => $reports->count(),
    //         'approved'  => $reports->where('status', 'approved')->count(),
    //         'submitted' => $reports->where('status', 'submitted')->count(),
    //         'rejected'  => $reports->where('status', 'rejected')->count(),
    //         'draft'     => $reports->where('status', 'draft')->count(),
    //         'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
    //     ];

    //     $year        = $request->year  ?? now()->year;
    //     $month       = $request->month ?? null;
    //     $periodLabel = $month
    //         ? $this->bulanLabel((int) $month) . ' ' . $year
    //         : 'Tahun ' . $year;

    //     $fileName = 'laporan-bulanan-santri-' . now()->format('Y-m-d') . '.pdf';

    //     $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.santri_monthly_report', [
    //         'santri'      => Auth::user(),
    //         'reports'     => $reports,
    //         'stats'       => $stats,
    //         'periodLabel' => $periodLabel,
    //         'generatedAt' => now()->format('d/m/Y H:i'),
    //     ])
    //         ->setPaper('a4', 'portrait')
    //         ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

    //     return $pdf->download($fileName);
    // }

    // kode 2
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
        $destinationPath = public_path('image/monthly-reports');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destinationPath, $fileName);
        return 'image/monthly-reports/' . $fileName;
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
    // INDEX — GET /api/pesantren/santri/monthly-reports
    // Sejajar: EmployeeMonthlyReportController::index()
    // Query: month, year, status, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $query = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month'))  $query->where('month',  $request->month);
        if ($request->filled('year'))   $query->where('year',   $request->year);
        if ($request->filled('status')) $query->where('status', $request->status);

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan',
            'data'    => $query->orderByDesc('year')
                ->orderByDesc('month')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    // ============================================================
    // SUMMARY — GET /api/pesantren/santri/monthly-reports/summary
    // Sejajar: EmployeeMonthlyReportController::summary()
    // Query: year (opsional)
    // ============================================================
    public function summary(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $reports = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->when($request->filled('year'), fn($q) => $q->where('year', $request->year))
            ->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'total'     => $reports->count(),
                'approved'  => $reports->where('status', 'approved')->count(),
                'rejected'  => $reports->where('status', 'rejected')->count(),
                'submitted' => $reports->where('status', 'submitted')->count(),
                'draft'     => $reports->where('status', 'draft')->count(),
                'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
            ],
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/monthly-reports/{id}
    // Sejajar: EmployeeMonthlyReportController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $report = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $report]);
    }

    // ============================================================
    // STORE — POST /api/pesantren/santri/monthly-reports
    // Buat draft laporan bulanan
    // Sejajar: EmployeeMonthlyReportController::store()
    // ============================================================
    public function store(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $validator = Validator::make($request->all(), [
            'month'       => 'required|integer|between:1,12',
            'year'        => 'required|integer',
            'target'      => 'required|string',
            'achievement' => 'required|string',
            'problem'     => 'required|string',
            'solution'    => 'required|string',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $exists = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->where('month', $request->month)
            ->where('year',  $request->year)
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan bulan ' . $request->month . '/' . $request->year . ' sudah ada',
            ], 422);
        }

        $data = [
            'company_id'  => Auth::user()->company_id,
            'user_id'     => Auth::id(),
            'month'       => $request->month,
            'year'        => $request->year,
            'target'      => $request->target,
            'achievement' => $request->achievement,
            'problem'     => $request->problem,
            'solution'    => $request->solution,
            'status'      => 'draft',
        ];

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        $report = MonthlyReport::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil dibuat sebagai draft',
            'data'    => $report,
        ], 201);
    }

    // ============================================================
    // UPDATE — POST /api/pesantren/santri/monthly-reports/{id}
    // Edit draft / laporan yang ditolak
    // Sejajar: EmployeeMonthlyReportController::update()
    // ============================================================
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureSantri();

        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if (!in_array($report->status, ['draft', 'rejected'])) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan yang sudah disubmit / diapprove tidak bisa diedit',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'target'      => 'sometimes|string',
            'achievement' => 'sometimes|string',
            'problem'     => 'sometimes|string',
            'solution'    => 'sometimes|string',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = array_filter([
            'target'      => $request->target,
            'achievement' => $request->achievement,
            'problem'     => $request->problem,
            'solution'    => $request->solution,
        ]);

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($report->attachment);
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        // Jika sebelumnya rejected, reset ke draft
        if ($report->status === 'rejected') {
            $data['status']      = 'draft';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            $data['score']       = 0;
        }

        $report->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil diupdate',
            'data'    => $report->fresh('approver:id,name'),
        ]);
    }

    // ============================================================
    // SUBMIT — PATCH /api/pesantren/santri/monthly-reports/{id}/submit
    // Submit draft ke ustadz
    // Sejajar: EmployeeMonthlyReportController::submit()
    // ============================================================
    public function submit(int $id): JsonResponse
    {
        $this->ensureSantri();

        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if ($report->status !== 'draft') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus draft yang bisa disubmit',
            ], 422);
        }

        $report->update(['status' => 'submitted']);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil disubmit ke ustadz',
            'data'    => $report->fresh(),
        ]);
    }

    // ============================================================
    // DESTROY — DELETE /api/pesantren/santri/monthly-reports/{id}
    // Sejajar: EmployeeMonthlyReportController::destroy()
    // ============================================================
    public function destroy(int $id): JsonResponse
    {
        $this->ensureSantri();

        $report = MonthlyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        if ($report->status !== 'draft') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus draft yang bisa dihapus',
            ], 422);
        }

        $this->deleteAttachment($report->attachment);
        $report->delete();

        return response()->json(['status' => true, 'message' => 'Laporan berhasil dihapus']);
    }

    // ============================================================
    // EXPORT — GET /api/pesantren/santri/monthly-reports/export
    // Sejajar: EmployeeMonthlyReportController::export()
    // Query: year, month, status (semua opsional)
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureSantri();

        $query = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($request->filled('year'))   $query->where('year',   $request->year);
        if ($request->filled('month'))  $query->where('month',  $request->month);
        if ($request->filled('status')) $query->where('status', $request->status);

        $reports = $query->get();

        $stats = [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round($reports->where('status', 'approved')->avg('score') ?? 0, 2),
        ];

        $year        = $request->year  ?? now()->year;
        $month       = $request->month ?? null;
        $periodLabel = $month
            ? $this->bulanLabel((int) $month) . ' ' . $year
            : 'Tahun ' . $year;

        $fileName = 'laporan-bulanan-santri-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.santri_monthly_report', [
            'santri'      => Auth::user(),
            'reports'     => $reports,
            'stats'       => $stats,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
