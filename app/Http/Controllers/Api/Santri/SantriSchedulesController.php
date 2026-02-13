<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use Carbon\Carbon;

class SantriSchedulesController extends Controller
{
    private function ensureSantri()
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus santri)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureSantri();

        $q = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id());

        if ($request->filled('from')) $q->whereDate('date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('date', '<=', $request->to);

        return response()->json([
            'status' => true,
            'message' => 'List schedule santri',
            'data' => $q->orderByDesc('date')->paginate(20),
        ]);
    }

    public function today()
    {
        $this->ensureSantri();
        $today = Carbon::today()->toDateString();

        $data = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->get();

        return response()->json(['status' => true, 'message' => 'Schedule hari ini', 'data' => $data]);
    }

    public function show($id)
    {
        $this->ensureSantri();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail schedule', 'data' => $schedule]);
    }
}
