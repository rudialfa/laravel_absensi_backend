<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HrCompanyDailyReportController extends Controller
{
    // ─── GET /hr/daily-reports ────────────────────────────────────────────────
    // Lihat semua daily report semua karyawan
    public function index(Request $request)
    {
        $query = DailyReport::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id);

        // Filter per karyawan
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter per tanggal spesifik
        if ($request->filled('date')) {
            $query->where('date', $request->date);
        }

        // Filter range (mingguan / custom)
        if ($request->filled('start') && $request->filled('end')) {
            $query->whereBetween('date', [$request->start, $request->end]);
        }

        // Filter bulanan
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('date', $request->month)
                ->whereYear('date', $request->year);
        }

        // Filter yang tidak tercapai saja
        if ($request->filled('is_achieved')) {
            $query->where('is_achieved', filter_var($request->is_achieved, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter per departemen
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }

        // Filter yang belum submit sore (belum ada achievement)
        if ($request->filled('pending_evening') && $request->pending_evening) {
            $query->whereNull('achievement');
        }

        $reports = $query->orderByDesc('date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil data daily report',
            'data'    => $reports,
        ]);
    }

    // ─── GET /hr/daily-reports/{id} ───────────────────────────────────────────
    // Detail satu daily report
    public function show($id)
    {
        $report = DailyReport::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $report,
        ]);
    }

    // ─── GET /hr/daily-reports/summary ───────────────────────────────────────
    // Rekap pencapaian semua karyawan per bulan
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

        return response()->json([
            'status' => true,
            'data'   => $summary,
        ]);
    }

    // ─── GET /hr/daily-reports/today ─────────────────────────────────────────
    // Snapshot hari ini: siapa yang sudah/belum submit pagi & sore
    public function today(Request $request)
    {
        $today = now()->toDateString();

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
}
