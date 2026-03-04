<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\PerformanceScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployeePerformanceScoreController extends Controller
{
    // ─── GET /employee/performance-scores ─────────────────────────────────────
    // Lihat semua skor performa milik sendiri
    public function index(Request $request)
    {
        $query = PerformanceScore::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        $scores = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => true,
            'data'   => $scores,
        ]);
    }

    // ─── GET /employee/performance-scores/{id} ────────────────────────────────
    // Detail satu skor
    public function show($id)
    {
        $score = PerformanceScore::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id()) // hanya milik sendiri
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data'   => $score,
        ]);
    }

    // ─── GET /employee/performance-scores/leaderboard ─────────────────────────
    // Lihat leaderboard bulan tertentu (semua karyawan, posisi diri sendiri)
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
                $item->is_me = $item->user_id === Auth::id(); // tandai posisi sendiri
                return $item;
            });

        // Cari posisi diri sendiri jika tidak masuk top 10
        $myScore = PerformanceScore::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        $myRank = null;
        if ($myScore) {
            $myRank = PerformanceScore::where('company_id', Auth::user()->company_id)
                ->where('month', $request->month)
                ->where('year', $request->year)
                ->where('final_score', '>', $myScore->final_score)
                ->count() + 1;
        }

        return response()->json([
            'status'   => true,
            'my_rank'  => $myRank,
            'my_score' => $myScore,
            'data'     => $leaderboard,
        ]);
    }
}
