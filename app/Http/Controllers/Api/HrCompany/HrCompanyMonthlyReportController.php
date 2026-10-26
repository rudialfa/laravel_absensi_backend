<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leaves;
use App\Models\MonthlyReport;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HrCompanyMonthlyReportController extends Controller
{
    private const USER_COLUMNS = 'user:id,name,position,department,image_url';

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    // Sebelumnya tidak ada cek role sama sekali
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

    private function stats($reports): array
    {
        return [
            'total'     => $reports->count(),
            'approved'  => $reports->where('status', 'approved')->count(),
            'rejected'  => $reports->where('status', 'rejected')->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'draft'     => $reports->where('status', 'draft')->count(),
            'avg_score' => round((float) ($reports->where('status', 'approved')->avg('score') ?? 0), 2),
        ];
    }

    /**
     * Data pendukung untuk HR saat menilai laporan:
     * absensi, izin, cuti, lembur, dan laporan harian di bulan yang sama.
     */
    private function monthContext(MonthlyReport $report): array
    {
        $start = Carbon::create($report->year, $report->month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
        $range = [$start->toDateString(), $end->toDateString()];

        $base = fn($q) => $q->where('company_id', $report->company_id)->where('user_id', $report->user_id);

        $att = $base(Attendance::query())->whereBetween('date', $range)
            ->get(['status', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'is_outside_radius_in']);

        $izin = $base(Permission::query())->where('is_approved', true)
            ->whereBetween('date_permission', $range)->count();

        $cuti = $base(Leaves::query())->where('status', 'approved')
            ->where('start_date', '<=', $range[1])
            ->where('end_date', '>=', $range[0])
            ->count();

        $daily = $base(DB::table('daily_reports'))->whereBetween('date', $range)
            ->selectRaw('COUNT(*) as total, SUM(is_achieved = 1) as achieved')
            ->first();

        $dailyTotal    = (int) ($daily->total ?? 0);
        $dailyAchieved = (int) ($daily->achieved ?? 0);

        return [
            'attendance' => [
                'hadir'               => $att->whereIn('status', ['on_time', 'late', 'overtime', 'guest'])->count(),
                'tepat_waktu'         => $att->whereIn('status', ['on_time', 'overtime', 'guest'])->count(),
                'terlambat'           => $att->where('status', 'late')->count(),
                'alpha'               => $att->where('status', 'absent')->count(),
                'dinas_luar'          => $att->where('is_outside_radius_in', true)->count(),
                'total_late_minutes'  => (int) $att->sum('late_minutes'),
                'total_early_minutes' => (int) $att->sum('early_leave_minutes'),
                'total_overtime_min'  => (int) $att->sum('overtime_minutes'),
            ],
            'izin_approved' => $izin,
            'cuti_approved' => $cuti,
            'daily_reports' => [
                'total'            => $dailyTotal,
                'achieved'         => $dailyAchieved,
                'achievement_rate' => $dailyTotal ? round($dailyAchieved / $dailyTotal * 100, 1) : 0,
            ],
        ];
    }

    // ============================================================
    // GET /api/company/hr/monthly-reports
    // filter: user_id, month, year, status, department, q, per_page
    //
    // Draft TIDAK ditampilkan (belum dikirim karyawan), kecuali status=draft.
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

        $query = MonthlyReport::with([self::USER_COLUMNS, 'approver:id,name'])
            ->where('company_id', $this->companyId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'draft');
        }

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('month'))   $query->where('month', $request->month);
        if ($request->filled('year'))    $query->where('year', $request->year);
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }
        if ($request->filled('q')) {
            $kw = $request->q;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%{$kw}%"));
        }

        $reports = $query
            ->orderByRaw("status = 'submitted' DESC") // menunggu review paling atas
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('submitted_at')
            ->paginate((int) $request->get('per_page', 15));

        $reports->getCollection()->transform(fn($r) => $r->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data laporan bulanan',
            'counts'  => MonthlyReport::where('company_id', $this->companyId())
                ->where('status', '!=', 'draft')
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/monthly-reports/{id}
    //
    // Detail sekarang berisi:
    //  - isi laporan + lampiran (attachment_url)
    //  - data karyawan
    //  - ringkasan absen / izin / cuti / laporan harian bulan tsb
    //  - riwayat nilai 6 laporan terakhir karyawan
    //  - can_approve
    // ============================================================
    public function show($id)
    {
        $this->ensureHr();

        $report = MonthlyReport::with([self::USER_COLUMNS, 'approver:id,name'])
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $history = MonthlyReport::where('company_id', $this->companyId())
            ->where('user_id', $report->user_id)
            ->where('id', '!=', $report->id)
            ->where('status', '!=', 'draft')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(6)
            ->get(['id', 'month', 'year', 'status', 'score'])
            ->map(fn($r) => [
                'id'           => $r->id,
                'period_label' => $r->period_label,
                'status'       => $r->status,
                'score'        => (float) $r->score,
            ])->values();

        return response()->json([
            'status'  => true,
            'message' => 'Detail laporan bulanan',
            'data'    => array_merge($report->toApiArray(), [
                'context'     => $this->monthContext($report),
                'history'     => $history,
                'can_approve' => $report->status === 'submitted',
            ]),
        ]);
    }

    // ============================================================
    // PATCH /api/company/hr/monthly-reports/{id}/approve
    // body: { score (0-100, wajib), note (opsional) }
    // ============================================================
    public function approve(Request $request, $id)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            'score' => 'required|numeric|min:0|max:100',
            'note'  => 'nullable|string|max:1000',
        ], [
            'score.required' => 'Nilai wajib diisi.',
            'score.max'      => 'Nilai maksimal 100.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $report = MonthlyReport::where('company_id', $this->companyId())->findOrFail($id);

        if ($report->status !== 'submitted') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan yang sudah dikirim karyawan yang bisa disetujui',
            ], 422);
        }

        $report->update([
            'status'        => 'approved',
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
            'score'         => $request->score,
            'approval_note' => $request->note,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan berhasil disetujui',
            'data'    => $report->fresh([self::USER_COLUMNS, 'approver:id,name'])->toApiArray(),
        ]);
    }

    // ============================================================
    // PATCH /api/company/hr/monthly-reports/{id}/reject
    // body: { note } — alasan penolakan WAJIB
    // (sebelumnya alasan tidak disimpan, karyawan tidak tahu harus memperbaiki apa)
    // ============================================================
    public function reject(Request $request, $id)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            'note' => 'required|string|min:3|max:1000',
        ], [
            'note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $report = MonthlyReport::where('company_id', $this->companyId())->findOrFail($id);

        if ($report->status !== 'submitted') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya laporan yang sudah dikirim karyawan yang bisa ditolak',
            ], 422);
        }

        $report->update([
            'status'        => 'rejected',
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
            'score'         => 0,
            'approval_note' => $request->note,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Laporan ditolak',
            'data'    => $report->fresh([self::USER_COLUMNS, 'approver:id,name'])->toApiArray(),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/monthly-reports/summary?month=&year=
    // Tambahan: daftar karyawan yang BELUM mengirim laporan bulan tsb
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

        $reports = MonthlyReport::where('company_id', $this->companyId())
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->get();

        $sentUserIds = $reports->where('status', '!=', 'draft')->pluck('user_id')->all();

        $notSubmitted = User::where('company_id', $this->companyId())
            ->where('role', 'employee')
            ->where('is_active', true)
            ->whereNotIn('id', $sentUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'department', 'image_url'])
            ->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'position'   => $u->position,
                'department' => $u->department,
                'image_url'  => $u->image_url ? asset($u->image_url) : null,
                'has_draft'  => $reports->where('user_id', $u->id)->where('status', 'draft')->isNotEmpty(),
            ])->values();

        return response()->json([
            'status'        => true,
            'period_label'  => (MonthlyReport::MONTH_NAMES[(int) $request->month] ?? '') . ' ' . $request->year,
            'stats'         => array_merge($this->stats($reports), [
                'not_submitted' => $notSubmitted->count(),
            ]),
            'not_submitted' => $notSubmitted,
            'data'          => $reports->where('status', '!=', 'draft')
                ->map(fn($r) => $r->toApiArray())
                ->values(),
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/monthly-reports/export?month=&year=&status=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $validator = Validator::make($request->all(), [
            'month'  => 'required|integer|between:1,12',
            'year'   => 'required|integer|min:2020|max:2099',
            'status' => 'nullable|in:draft,submitted,approved,rejected',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $month  = (int) $request->month;
        $year   = (int) $request->year;
        $status = $request->status ?? null;

        $query = MonthlyReport::where('company_id', $this->companyId())
            ->where('month', $month)
            ->where('year', $year)
            ->with(['user:id,name,position,department', 'approver:id,name'])
            ->orderBy('user_id');

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->where('status', '!=', 'draft');
        }

        $reports = $query->get();

        $periodLabel = (MonthlyReport::MONTH_NAMES[$month] ?? $month) . ' ' . $year;
        $statusLabel = $status ? ' - ' . ucfirst($status) : '';
        $fileName    = 'monthly-report-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_monthly_report', [
            'company'     => Auth::user()->company ?? (object) ['name' => ''],
            'periodLabel' => $periodLabel . $statusLabel,
            'reports'     => $reports,
            'stats'       => $this->stats($reports),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
