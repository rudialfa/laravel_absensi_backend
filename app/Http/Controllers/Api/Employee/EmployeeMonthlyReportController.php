<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class EmployeeMonthlyReportController extends Controller
{

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

    // // GET /employee/monthly-reports
    // public function index(Request $request)
    // {
    //     $query = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id());

    //     if ($request->filled('month'))  $query->where('month', $request->month);
    //     if ($request->filled('year'))   $query->where('year', $request->year);
    //     if ($request->filled('status')) $query->where('status', $request->status);

    //     $reports = $query->orderByDesc('year')
    //         ->orderByDesc('month')
    //         ->paginate($request->get('per_page', 15));

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Berhasil mengambil data laporan bulanan',
    //         'data'    => $reports,
    //     ]);
    // }

    // // GET /employee/monthly-reports/summary
    // public function summary(Request $request)
    // {
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

    // // GET /employee/monthly-reports/{id}
    // public function show($id)
    // {
    //     $report = MonthlyReport::with('approver:id,name')
    //         ->where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->findOrFail($id);

    //     return response()->json(['status' => true, 'data' => $report]);
    // }

    // // POST /employee/monthly-reports — Buat draft baru
    // public function store(Request $request)
    // {
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

    //     // Cek duplikat bulan
    //     $exists = MonthlyReport::where('company_id', Auth::user()->company_id)
    //         ->where('user_id', Auth::id())
    //         ->where('month', $request->month)
    //         ->where('year', $request->year)
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

    // // POST /employee/monthly-reports/{id} — Edit (hanya draft/rejected)
    // public function update(Request $request, $id)
    // {
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

    //     // Jika rejected → kembali ke draft saat diedit
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

    // // PATCH /employee/monthly-reports/{id}/submit
    // public function submit($id)
    // {
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
    //         'message' => 'Laporan berhasil disubmit ke HR',
    //         'data'    => $report->fresh(),
    //     ]);
    // }

    // // DELETE /employee/monthly-reports/{id}
    // public function destroy($id)
    // {
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


    // KODE 2
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

    // GET /employee/monthly-reports
    public function index(Request $request)
    {
        $query = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month'))  $query->where('month', $request->month);
        if ($request->filled('year'))   $query->where('year', $request->year);
        if ($request->filled('status')) $query->where('status', $request->status);

        $reports = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan',
            'data'    => $reports,
        ]);
    }

    // GET /employee/monthly-reports/summary
    public function summary(Request $request)
    {
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

    // GET /employee/monthly-reports/{id}
    public function show($id)
    {
        $report = MonthlyReport::with('approver:id,name')
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $report]);
    }

    // POST /employee/monthly-reports
    public function store(Request $request)
    {
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
            ->where('year', $request->year)
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

    // POST /employee/monthly-reports/{id}
    public function update(Request $request, $id)
    {
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

    // PATCH /employee/monthly-reports/{id}/submit
    public function submit($id)
    {
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
            'message' => 'Laporan berhasil disubmit ke HR',
            'data'    => $report->fresh(),
        ]);
    }

    // DELETE /employee/monthly-reports/{id}
    public function destroy($id)
    {
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

    // ─── EXPORT PDF ────────────────────────────────────────────────────────────
    // GET /api/company/employee/monthly-reports/export
    // Query: year, month, status (semua opsional)
    // composer require barryvdh/laravel-dompdf
    // ───────────────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
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

        $year        = $request->year  ?? now()->year;
        $month       = $request->month ?? null;
        $periodLabel = $month
            ? ($bulanLabel[$month] ?? $month) . ' ' . $year
            : 'Tahun ' . $year;

        $fileName = 'monthly-report-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.employee_monthly_report', [
            'user'        => Auth::user(),
            'reports'     => $reports,
            'stats'       => $stats,
            'bulanLabel'  => $bulanLabel,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
