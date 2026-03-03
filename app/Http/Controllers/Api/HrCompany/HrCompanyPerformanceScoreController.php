<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\PerformanceScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HrCompanyPerformanceScoreController extends Controller
{
    // ─── GET /hr/performance-scores ───────────────────────────────────────────
    // Lihat semua skor performa karyawan
    public function index(Request $request)
    {
        $query = PerformanceScore::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }

        $scores = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('final_score')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'data'   => $scores,
        ]);
    }

    // ─── GET /hr/performance-scores/{id} ──────────────────────────────────────
    // Detail skor satu record
    public function show($id)
    {
        $score = PerformanceScore::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $score,
        ]);
    }

    // ─── POST /hr/performance-scores/generate ─────────────────────────────────
    // Generate / recalculate skor dari data daily report bulan tertentu
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month'   => 'required|integer|between:1,12',
            'year'    => 'required|integer',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $companyId = Auth::user()->company_id;

        $query = DailyReport::where('company_id', $companyId)
            ->whereMonth('date', $request->month)
            ->whereYear('date', $request->year)
            ->whereNotNull('achievement') // hanya yang sudah submit sore
            ->selectRaw('
                user_id,
                COUNT(*) as total_targets,
                SUM(CASE WHEN is_achieved = 1 THEN 1 ELSE 0 END) as targets_achieved
            ')
            ->groupBy('user_id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $rows      = $query->get();
        $generated = [];

        foreach ($rows as $row) {
            $rate = $row->total_targets > 0
                ? round(($row->targets_achieved / $row->total_targets) * 100, 2)
                : 0;

            $score = PerformanceScore::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'user_id'    => $row->user_id,
                    'year'       => $request->year,
                    'month'      => $request->month,
                ],
                [
                    'total_targets'    => $row->total_targets,
                    'targets_achieved' => $row->targets_achieved,
                    'achievement_rate' => $rate,
                    'final_score'      => $rate,
                ]
            );

            $generated[] = $score->load('user:id,name,department,position');
        }

        return response()->json([
            'status'  => true,
            'message' => count($generated) . ' skor berhasil digenerate',
            'data'    => $generated,
        ]);
    }

    // ─── GET /hr/performance-scores/leaderboard ───────────────────────────────
    // Top performer bulan tertentu
    public function leaderboard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $leaderboard = PerformanceScore::with('user:id,name,department,position,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->orderByDesc('final_score')
            ->limit($request->get('limit', 10))
            ->get()
            ->map(function ($item, $index) {
                $item->rank = $index + 1;
                return $item;
            });

        return response()->json([
            'status' => true,
            'data'   => $leaderboard,
        ]);
    }
}
