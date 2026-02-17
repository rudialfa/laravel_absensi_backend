<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use Carbon\Carbon;

class SantriSchedulesController extends Controller
{

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus santri)'
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan'
            ], 422));
        }

        return $companyId;
    }

    // =========================
    // LIST (paginate)
    // GET ?from=YYYY-MM-DD&to=YYYY-MM-DD
    // =========================
    public function index(Request $request)
    {
        $this->ensureSantri();

        $q = Schedule::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id());

        // ✅ dari kolom start_datetime, bukan date
        if ($request->filled('from')) {
            $q->whereDate('start_datetime', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('start_datetime', '<=', $request->to);
        }

        return response()->json([
            'status' => true,
            'message' => 'List schedule santri',
            // ✅ order by start_datetime, bukan date
            'data' => $q->orderByDesc('start_datetime')->paginate(20),
        ]);
    }

    // =========================
    // TODAY
    // =========================
    public function today()
    {
        $this->ensureSantri();

        $today = Carbon::today()->toDateString();

        $data = Schedule::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            // ✅ whereDate start_datetime, bukan date
            ->whereDate('start_datetime', $today)
            // ✅ orderBy start_datetime, bukan start_time
            ->orderBy('start_datetime', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Schedule hari ini',
            'data' => $data
        ]);
    }

    // =========================
    // DETAIL
    // =========================
    public function show($id)
    {
        $this->ensureSantri();

        $schedule = Schedule::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail schedule',
            'data' => $schedule
        ]);
    }
}
