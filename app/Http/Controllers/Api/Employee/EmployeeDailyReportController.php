<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployeeDailyReportController extends Controller
{
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
        return DailyReport::query()
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

    /**
     * Aplikasi (multipart) sering mengirim is_achieved = "true" / "false" (string).
     * Aturan 'boolean' Laravel MENOLAK string "true"/"false" → sore selalu gagal disimpan.
     * Di sini dinormalisasi dulu jadi true/false.
     */
    private function normalizeBoolean(Request $request, string $key): void
    {
        if ($request->has($key)) {
            $val = filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $request->merge([$key => $val]);
        }
    }

    // ============================================================
    // GET /api/company/employee/daily-reports
    // filter: date, start, end, month, year, is_achieved, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $query = $this->myQuery();

        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                ->whereYear('date', $request->year);
        }
        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN))
                ->whereNotNull('achievement');
        }

        $reports = $query->orderByDesc('date')->paginate((int) $request->get('per_page', 15));
        $reports->getCollection()->transform(fn($r) => $r->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data daily report',
            'data'    => $reports,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/daily-reports/today
    // ============================================================
    public function today()
    {
        $this->ensureEmployee();

        $today  = now()->toDateString();
        $report = $this->myQuery()->whereDate('date', $today)->first();

        // Laporan kemarin yang belum diisi sore (masih bisa diisi sampai hari ini)
        $yesterday = $this->myQuery()
            ->whereDate('date', now()->subDay()->toDateString())
            ->whereNull('achievement')
            ->first();

        return response()->json([
            'status' => true,
            'date'   => $today,
            'data'   => $report?->toApiArray(),
            'info'   => [
                'submitted_morning'        => $report !== null,
                'submitted_evening'        => $report !== null && $report->achievement !== null,
                'is_achieved'              => (bool) ($report?->is_achieved ?? false),
                'yesterday_pending_id'     => $yesterday?->id,
                'yesterday_pending_target' => $yesterday?->target,
            ],
        ]);
    }

    // ============================================================
    // GET /api/company/employee/daily-reports/summary?month=&year=
    // ============================================================
    public function summary(Request $request)
    {
        $this->ensureEmployee();

        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $row = $this->myQuery()
            ->whereMonth('date', $request->month)
            ->whereYear('date', $request->year)
            ->selectRaw('
                COUNT(*) as total_days,
                SUM(CASE WHEN is_achieved = 1 AND achievement IS NOT NULL THEN 1 ELSE 0 END) as total_achieved,
                SUM(CASE WHEN is_achieved = 0 AND achievement IS NOT NULL THEN 1 ELSE 0 END) as total_not_achieved,
                SUM(CASE WHEN achievement IS NULL THEN 1 ELSE 0 END) as total_pending
            ')
            ->first();

        $achieved    = (int) ($row->total_achieved ?? 0);
        $notAchieved = (int) ($row->total_not_achieved ?? 0);
        $filled      = $achieved + $notAchieved;

        return response()->json([
            'status' => true,
            'data'   => [
                'total_days'         => (int) ($row->total_days ?? 0),
                'total_achieved'     => $achieved,
                'total_not_achieved' => $notAchieved,
                'total_pending'      => (int) ($row->total_pending ?? 0),
                'achievement_rate'   => $filled ? round($achieved / $filled * 100, 2) : 0,
            ],
        ]);
    }

    // ============================================================
    // GET /api/company/employee/daily-reports/{id}
    // ============================================================
    public function show($id)
    {
        $this->ensureEmployee();

        $report = $this->myQuery()->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail laporan harian',
            'data'    => $report->toApiArray(),
        ]);
    }

    // ============================================================
    // POST /api/company/employee/daily-reports — Target pagi (multipart)
    // field: target, attachment (opsional)
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validator = Validator::make($request->all(), [
            'target'     => 'required|string|min:3|max:5000',
            'attachment' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'target.required' => 'Target hari ini wajib diisi.',
            'target.min'      => 'Target minimal 3 karakter.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $today = now()->toDateString();

        $existing = $this->myQuery()->whereDate('date', $today)->first();

        if ($existing) {
            return response()->json([
                'status'      => false,
                'message'     => 'Kamu sudah mengisi target hari ini',
                'existing_id' => $existing->id,
            ], 422);
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

        try {
            $report = DailyReport::create($data);
        } catch (\Throwable $e) {
            $this->deleteAttachment($data['attachment'] ?? null);
            throw $e;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Target pagi berhasil disimpan',
            'data'    => $report->toApiArray(),
        ], 201);
    }

    // ============================================================
    // POST /api/company/employee/daily-reports/{id}   (multipart)
    //
    // Dua mode:
    //  A. Isi pencapaian sore  → kirim achievement + is_achieved (+ reason_not_achieved)
    //  B. Edit target pagi     → kirim target saja (hanya sebelum sore diisi)
    //
    // Batas waktu: laporan bisa diisi/diedit sampai akhir HARI BERIKUTNYA.
    // ============================================================
    public function update(Request $request, $id)
    {
        $this->ensureEmployee();

        $report = $this->myQuery()->findOrFail($id);

        if (!$report->can_update) {
            return response()->json([
                'status'  => false,
                'message' => 'Laporan tanggal ' . $report->date->format('d/m/Y') . ' sudah tidak bisa diubah.',
            ], 422);
        }

        $this->normalizeBoolean($request, 'is_achieved');

        // ── Mode B: edit target saja ──────────────────────────────
        if (!$request->has('achievement') && $request->has('target')) {
            if ($report->achievement !== null) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Target tidak bisa diubah setelah pencapaian sore diisi.',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'target'     => 'required|string|min:3|max:5000',
                'attachment' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator);
            }

            $data = ['target' => $request->target];

            if ($request->hasFile('attachment')) {
                $this->deleteAttachment($report->attachment);
                $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
            }

            $report->update($data);

            return response()->json([
                'status'  => true,
                'message' => 'Target berhasil diupdate',
                'data'    => $report->fresh()->toApiArray(),
            ]);
        }

        // ── Mode A: pencapaian sore ───────────────────────────────
        $validator = Validator::make($request->all(), [
            'achievement'         => 'required|string|min:3|max:5000',
            'is_achieved'         => 'required|boolean',
            // Sebelumnya required_if:is_achieved,false → tidak jalan kalau aplikasi kirim "0"
            'reason_not_achieved' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf($request->input('is_achieved') === false),
            ],
            'attachment'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'achievement.required'         => 'Pencapaian hari ini wajib diisi.',
            'is_achieved.required'         => 'Pilih apakah target tercapai atau tidak.',
            'is_achieved.boolean'          => 'Nilai tercapai harus ya / tidak.',
            'reason_not_achieved.required' => 'Alasan wajib diisi kalau target tidak tercapai.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $isAchieved = (bool) $request->input('is_achieved');

        $data = [
            'achievement'         => $request->achievement,
            'is_achieved'         => $isAchieved,
            'reason_not_achieved' => $isAchieved ? null : $request->reason_not_achieved,
        ];

        if ($request->hasFile('attachment')) {
            $this->deleteAttachment($report->attachment);
            $data['attachment'] = $this->uploadAttachment($request->file('attachment'));
        }

        $report->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Pencapaian sore berhasil disimpan',
            'data'    => $report->fresh()->toApiArray(),
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/employee/daily-reports/export
    // filter: month+year | start+end | year, is_achieved
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureEmployee();

        $query = $this->myQuery();

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                ->whereYear('date', $request->year);
        } elseif ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        } elseif ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN))
                ->whereNotNull('achievement');
        }

        $reports = $query->orderByDesc('date')->get();

        $stats = [
            'total'           => $reports->count(),
            'achieved'        => $reports->where('is_achieved', true)->whereNotNull('achievement')->count(),
            'not_achieved'    => $reports->where('is_achieved', false)->whereNotNull('achievement')->count(),
            'pending_evening' => $reports->whereNull('achievement')->count(),
        ];

        $filled          = $stats['achieved'] + $stats['not_achieved'];
        $achievementRate = $filled > 0 ? round($stats['achieved'] / $filled * 100, 1) : 0;

        $month       = $request->month ? (int) $request->month : null;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? (DailyReport::MONTH_NAMES[$month] ?? $month) . ' ' . $year
            : ($request->filled('start') && $request->filled('end')
                ? $request->start . ' s/d ' . $request->end
                : ($request->filled('year') ? 'Tahun ' . $year : 'Semua Periode'));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.employee_daily_report', [
            'user'            => Auth::user(),
            'reports'         => $reports,
            'stats'           => $stats,
            'achievementRate' => $achievementRate,
            'periodLabel'     => $periodLabel,
            'generatedAt'     => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('daily-report-' . now()->format('Y-m-d') . '.pdf');
    }
}
