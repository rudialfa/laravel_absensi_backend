<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class EmployeeDailyReportController extends Controller
{
    // ─── HELPER: Upload attachment ────────────────────────────────────────────
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

    // ─── GET /employee/daily-reports ──────────────────────────────────────────
    // Lihat semua daily report milik sendiri
    public function index(Request $request)
    {
        $query = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        // Filter per tanggal spesifik
        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        // Filter range tanggal
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        }

        // Filter bulanan
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                ->whereYear('date', $request->year);
        }

        // Filter pencapaian
        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN));
        }

        $reports = $query->orderByDesc('date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data daily report',
            'data'    => $reports,
        ]);
    }

    // ─── GET /employee/daily-reports/today ────────────────────────────────────
    // Cek status laporan hari ini milik sendiri
    public function today()
    {
        $today = now()->toDateString();

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

    // ─── GET /employee/daily-reports/summary ──────────────────────────────────
    // Rekap pencapaian sendiri per bulan
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
            ->where('user_id', Auth::id())
            ->whereMonth('date', $request->month)
            ->whereYear('date', $request->year)
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

    // ─── GET /employee/daily-reports/{id} ─────────────────────────────────────
    public function show($id)
    {
        $report = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $report,
        ]);
    }

    // ─── POST /employee/daily-reports ─────────────────────────────────────────
    // Submit target pagi — hanya bisa 1x per hari
    public function store(Request $request)
    {
        $today = now()->toDateString();

        // Cek sudah submit hari ini belum
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

    // ─── POST /employee/daily-reports/{id} ────────────────────────────────────
    // Update pencapaian sore — pakai POST karena bisa ada file upload
    public function update(Request $request, $id)
    {
        $report = DailyReport::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        // Hanya bisa update jika belum ada achievement (belum submit sore)
        // Comment baris ini jika ingin bisa edit berkali-kali
        // if ($report->achievement !== null) {
        //     return response()->json([
        //         'status'  => false,
        //         'message' => 'Pencapaian hari ini sudah disubmit',
        //     ], 422);
        // }

        $validator = Validator::make($request->all(), [
            'achievement'          => 'required|string',
            'is_achieved'          => 'required|boolean',
            'reason_not_achieved'  => 'nullable|string|required_if:is_achieved,false',
            'attachment'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
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
}
