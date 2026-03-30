<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\PerformanceScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class Santriperformancecontroller extends Controller
{
    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus Santri)'], 403));
        }
    }

    // GET /api/pesantren/santri/performance
    // Sejajar: EmployeePerformanceScoreController::index()
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $query = PerformanceScore::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id());

        if ($request->filled('month')) $query->where('month', $request->month);
        if ($request->filled('year'))  $query->where('year',  $request->year);

        return response()->json([
            'status' => true,
            'data'   => $query->orderByDesc('year')
                ->orderByDesc('month')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }

    // GET /api/pesantren/santri/performance/{id}
    // Sejajar: EmployeePerformanceScoreController::show()
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $score = PerformanceScore::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'data' => $score]);
    }

    // GET /api/pesantren/santri/performance/leaderboard
    // Sejajar: EmployeePerformanceScoreController::leaderboard()
    // Santri bisa lihat leaderboard pesantren + posisi diri sendiri
    public function leaderboard(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $leaderboard = PerformanceScore::with('user:id,name,department,position,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->where('month', $request->month)
            ->where('year',  $request->year)
            ->orderByDesc('final_score')
            ->limit((int) $request->get('limit', 10))
            ->get()
            ->map(function ($item, $index) {
                $item->rank  = $index + 1;
                $item->is_me = $item->user_id === Auth::id();
                return $item;
            });

        // Cari posisi diri sendiri jika tidak masuk top 10
        $myScore = PerformanceScore::where('company_id', Auth::user()->company_id)
            ->where('user_id', Auth::id())
            ->where('month', $request->month)
            ->where('year',  $request->year)
            ->first();

        $myRank = null;
        if ($myScore) {
            $myRank = PerformanceScore::where('company_id', Auth::user()->company_id)
                ->whereHas('user', fn($q) => $q->where('role', 'santri'))
                ->where('month', $request->month)
                ->where('year',  $request->year)
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
