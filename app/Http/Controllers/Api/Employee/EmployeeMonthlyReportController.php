<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\MonthlyReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class EmployeeMonthlyReportController extends Controller
{
    private const FIELDS = ['target', 'achievement', 'problem', 'solution'];

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    private function ensureEmployee(): void
    {
        if (!Auth::check() || Auth::user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function myQuery()
    {
        return MonthlyReport::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());
    }

    private function validationError($validator)
    {
        return response()->json([
            'status'  => false,
            'message' => $validator->errors()->first(),
            'errors'  => $validator->errors(),
        ], 422);
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

    private const MESSAGES = [
        'month.required'       => 'Bulan wajib dipilih.',
        'year.required'        => 'Tahun wajib diisi.',
        'target.required'      => 'Target wajib diisi.',
        'achievement.required' => 'Pencapaian wajib diisi.',
        'problem.required'     => 'Permasalahan wajib diisi.',
        'solution.required'    => 'Penyelesaian wajib diisi.',
        'attachment.mimes'     => 'Lampiran harus JPG, PNG, atau PDF.',
        'attachment.max'       => 'Lampiran maksimal 5 MB.',
    ];

    // ============================================================
    // GET /api/company/employee/monthly-reports
    // filter: month, year, status, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $query = $this->myQuery()->with('approver:id,name');

        if ($request->filled('month'))  $query->where('month', $request->month);
        if ($request->filled('year'))   $query->where('year', $request->year);
        if ($request->filled('status')) $query->where('status', $request->status);

        $reports = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate((int) $request->get('per_page', 15));

        $reports->getCollection()->transform(fn($r) => $r->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan',
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/monthly-reports/summary?year=
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureEmployee();

        $year = (int) $request->get('year', now()->year);

        $reports = $this->myQuery()->where('year', $year)->get();

        // Bulan yang belum ada laporan (sampai bulan berjalan)
        $lastMonth = $year < now()->year ? 12 : ($year == now()->year ? now()->month : 0);
        $filled    = $reports->pluck('month')->map(fn($m) => (int) $m)->all();
        $missing   = $lastMonth === 0
            ? collect()
            : collect(range(1, $lastMonth))
            ->reject(fn($m) => in_array($m, $filled, true))
            ->map(fn($m) => ['month' => $m, 'label' => MonthlyReport::MONTH_NAMES[$m] . ' ' . $year])
            ->values();

        return response()->json([
            'status' => true,
            'data'   => [
                'year'           => $year,
                'total'          => $reports->count(),
                'approved'       => $reports->where('status', 'approved')->count(),
                'rejected'       => $reports->where('status', 'rejected')->count(),
                'submitted'      => $reports->where('status', 'submitted')->count(),
                'draft'          => $reports->where('status', 'draft')->count(),
                'avg_score'      => round((float) ($reports->where('status', 'approved')->avg('score') ?? 0), 2),
                'missing_months' => $missing,
            ],
        ]);
    }

    // ============================================================
    // GET /api/company/employee/monthly-reports/{id}
    // ============================================================
    public function show($id)
    {
        $this->ensureEmployee();

        $report = $this->myQuery()->with('approver:id,name')->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail laporan bulanan',
            'data'    => $report->toApiArray(),
        ]);
    }

    // ============================================================
    // POST /api/company/employee/monthly-reports   (multipart)
    // field: month, year, target, achievement, problem, solution,
    //        attachment (opsional), submit (opsional: 1 = langsung kirim ke HR)
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validator = Validator::make($request->all(), [
            'month'       => 'required|integer|between:1,12',
            'year'        => 'required|integer|min:2020|max:2099',
            'target'      => 'required|string|max:5000',
            'achievement' => 'required|string|max:5000',
            'problem'     => 'required|string|max:5000',
            'solution'    => 'required|string|max:5000',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'submit'      => 'nullable|boolean',
        ], self::MESSAGES);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $month = (int) $request->month;
        $year  = (int) $request->year;

        // Tidak boleh membuat laporan untuk bulan yang belum datang
        if (Carbon::create($year, $month, 1)->gt(now()->startOfMonth())) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan untuk bulan yang belum berjalan tidak bisa dibuat.',
            ], 422);
        }

        $existing = $this->myQuery()->where('month', $month)->where('year', $year)->first();

        if ($existing) {
            return response()->json([
                'status'      => false,
                'message'     => 'Laporan ' . MonthlyReport::MONTH_NAMES[$month] . ' ' . $year
                    . ' sudah ada. Silakan edit laporan tersebut.',
                'existing_id' => $existing->id,
            ], 422);
        }

        $submitNow = $request->boolean('submit');

        $data = [
            'company_id'   => Auth::user()->company_id,
            'user_id'      => Auth::id(),
            'month'        => $month,
            'year'         => $year,
            'target'       => $request->target,
            'achievement'  => $request->achievement,
            'problem'      => $request->problem,
            'solution'     => $request->solution,
            'status'       => $submitNow ? 'submitted' : 'draft',
            'submitted_at' => $submitNow ? now() : null,
            'score'        => 0,
        ];

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        try {
            $report = MonthlyReport::create($data);
        } catch (\Throwable $e) {
            $this->deleteAttachment($data['attachment'] ?? null);
            throw $e;
        }

        return response()->json([
            'status'  => true,
            'message' => $submitNow
                ? 'Laporan berhasil dibuat dan dikirim ke HR'
                : 'Laporan berhasil disimpan sebagai draft',
            'data'    => $report->fresh('approver:id,name')->toApiArray(),
        ], 201);
    }

    // ============================================================
    // POST /api/company/employee/monthly-reports/{id}   (multipart)
    // Hanya draft / rejected. Laporan rejected yang diedit kembali jadi draft.
    // field: target, achievement, problem, solution, attachment,
    //        remove_attachment (1 = hapus lampiran), submit (1 = langsung kirim)
    // ============================================================
    public function update(Request $request, $id)
    {
        $this->ensureEmployee();

        $report = $this->myQuery()->findOrFail($id);

        if (!in_array($report->status, ['draft', 'rejected'], true)) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan yang sudah dikirim / disetujui tidak bisa diedit',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'target'            => 'sometimes|required|string|max:5000',
            'achievement'       => 'sometimes|required|string|max:5000',
            'problem'           => 'sometimes|required|string|max:5000',
            'solution'          => 'sometimes|required|string|max:5000',
            'attachment'        => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'remove_attachment' => 'nullable|boolean',
            'submit'            => 'nullable|boolean',
        ], self::MESSAGES);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = [];
        foreach (self::FIELDS as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($report->attachment);
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        } elseif ($request->boolean('remove_attachment')) {
            $this->deleteAttachment($report->attachment);
            $data['attachment'] = null;
        }

        // Laporan ditolak → diperbaiki → kembali jadi draft.
        // approval_note TIDAK dihapus supaya alasan penolakan sebelumnya tetap terlihat.
        if ($report->status === 'rejected') {
            $data['status']      = 'draft';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
            $data['score']       = 0;
        }

        if ($request->boolean('submit')) {
            $data['status']       = 'submitted';
            $data['submitted_at'] = now();
        }

        $report->update($data);

        return response()->json([
            'status'  => true,
            'message' => $request->boolean('submit')
                ? 'Laporan berhasil diupdate dan dikirim ke HR'
                : 'Laporan berhasil diupdate',
            'data'    => $report->fresh('approver:id,name')->toApiArray(),
        ]);
    }

    // ============================================================
    // PATCH /api/company/employee/monthly-reports/{id}/submit
    // ============================================================
    public function submit($id)
    {
        $this->ensureEmployee();

        $report = $this->myQuery()->findOrFail($id);

        if ($report->status !== 'draft') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan berstatus draft yang bisa dikirim',
            ], 422);
        }

        foreach (self::FIELDS as $field) {
            if (blank($report->{$field})) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Lengkapi semua isian laporan sebelum dikirim ke HR.',
                ], 422);
            }
        }

        $report->update([
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil dikirim ke HR',
            'data'    => $report->fresh('approver:id,name')->toApiArray(),
        ]);
    }

    // ============================================================
    // DELETE /api/company/employee/monthly-reports/{id}
    // ============================================================
    public function destroy($id)
    {
        $this->ensureEmployee();

        $report = $this->myQuery()->findOrFail($id);

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
    // EXPORT PDF
    // GET /api/company/employee/monthly-reports/export?year=&month=&status=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureEmployee();

        $query = $this->myQuery()
            ->with('approver:id,name')
            ->orderByDesc('year')
            ->orderByDesc('month');

        if ($request->filled('year'))   $query->where('year', $request->year);
        if ($request->filled('month'))  $query->where('month', $request->month);
        if ($request->filled('status')) $query->where('status', $request->status);

        $reports = $query->get();

        $stats = [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round((float) ($reports->where('status', 'approved')->avg('score') ?? 0), 2),
        ];

        $year        = $request->year ?? now()->year;
        $month       = $request->month ? (int) $request->month : null;
        $periodLabel = $month
            ? (MonthlyReport::MONTH_NAMES[$month] ?? $month) . ' ' . $year
            : ($request->filled('year') ? 'Tahun ' . $year : 'Semua Periode');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.employee_monthly_report', [
            'user'        => Auth::user(),
            'reports'     => $reports,
            'stats'       => $stats,
            'bulanLabel'  => MonthlyReport::MONTH_NAMES,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('monthly-report-' . now()->format('Y-m-d') . '.pdf');
    }
}
