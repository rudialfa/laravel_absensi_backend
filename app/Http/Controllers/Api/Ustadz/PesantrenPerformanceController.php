<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\DailyReport;
use App\Models\PerformanceScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;

class PesantrenPerformanceController extends Controller
{
     // ============================================================
    // PRIVATE HELPERS
    // ============================================================
 
    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }
 
    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
            4  => 'April',     5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',      8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',   11 => 'November',  12 => 'Desember',
        ][$month] ?? (string) $month;
    }
 
    // ============================================================
    // INDEX — GET /api/pesantren/performance
    // Sejajar: HrCompanyPerformanceScoreController::index()
    // Query: user_id, month, year, department (kelas), per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureUstadz();
 
        $query = PerformanceScore::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'));
 
        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('month'))      $query->where('month', $request->month);
        if ($request->filled('year'))       $query->where('year',  $request->year);
        if ($request->filled('department')) {
            $query->whereHas('user', fn($q) => $q->where('department', $request->department));
        }
 
        return response()->json([
            'status' => true,
            'data'   => $query->orderByDesc('year')
                ->orderByDesc('month')
                ->orderByDesc('final_score')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }
 
    // ============================================================
    // SHOW — GET /api/pesantren/performance/{id}
    // Sejajar: HrCompanyPerformanceScoreController::show()
    // ============================================================
    public function show(int $id)
    {
        $this->ensureUstadz();
 
        $score = PerformanceScore::with('user:id,name,position,department,image_url')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->findOrFail($id);
 
        return response()->json([
            'status' => true,
            'data'   => $score,
        ]);
    }
 
    // ============================================================
    // GENERATE — POST /api/pesantren/performance/generate
    // Generate skor dari daily report santri
    // Sejajar: HrCompanyPerformanceScoreController::generate()
    // Body: month (required), year (required), user_id (opsional)
    // ============================================================
    public function generate(Request $request)
    {
        $this->ensureUstadz();
 
        $validator = Validator::make($request->all(), [
            'month'   => 'required|integer|between:1,12',
            'year'    => 'required|integer',
            'user_id' => 'nullable|exists:users,id',
        ]);
 
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
 
        $companyId = Auth::user()->company_id;
 
        $query = DailyReport::where('company_id', $companyId)
            ->whereMonth('date', $request->month)
            ->whereYear('date',  $request->year)
            ->whereNotNull('achievement')
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->selectRaw('
                user_id,
                COUNT(*) as total_targets,
                SUM(CASE WHEN is_achieved = 1 THEN 1 ELSE 0 END) as targets_achieved
            ')
            ->groupBy('user_id');
 
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
 
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
            'message' => count($generated) . ' skor santri berhasil digenerate',
            'data'    => $generated,
        ]);
    }
 
    // ============================================================
    // LEADERBOARD — GET /api/pesantren/performance/leaderboard
    // Sejajar: HrCompanyPerformanceScoreController::leaderboard()
    // Query: month (required), year (required), limit (default 10)
    // ============================================================
    public function leaderboard(Request $request)
    {
        $this->ensureUstadz();
 
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer',
        ]);
 
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
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
                $item->rank = $index + 1;
                return $item;
            });
 
        return response()->json([
            'status' => true,
            'data'   => $leaderboard,
        ]);
    }
 
    // ============================================================
    // EXPORT — GET /api/pesantren/performance/export
    // Sejajar: HrCompanyPerformanceScoreController::export()
    // Query: month (required), year (required)
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();
 
        $validator = Validator::make($request->all(), [
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);
 
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
 
        $month = (int) $request->month;
        $year  = (int) $request->year;
 
        $scores = PerformanceScore::with('user:id,name,position,department')
            ->where('company_id', Auth::user()->company_id)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->where('month', $month)
            ->where('year',  $year)
            ->orderByDesc('final_score')
            ->get()
            ->map(function ($item, $index) {
                $item->rank = $index + 1;
                return $item;
            });
 
        $stats = [
            'total'     => $scores->count(),
            'avg_score' => round($scores->avg('final_score') ?? 0, 2),
            'max_score' => round($scores->max('final_score') ?? 0, 2),
            'min_score' => round($scores->min('final_score') ?? 0, 2),
            'above_80'  => $scores->where('final_score', '>=', 80)->count(),
            'below_50'  => $scores->where('final_score', '<',  50)->count(),
        ];
 
        $periodLabel = $this->bulanLabel($month) . ' ' . $year;
        $fileName    = 'nilai-santri-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';
 
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_performance', [
            'company'     => Auth::user()->company ?? (object)['name' => ''],
            'periodLabel' => $periodLabel,
            'scores'      => $scores,
            'stats'       => $stats,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);
 
        return $pdf->download($fileName);
    }
}
