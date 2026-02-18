<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ShiftAssignment;
use Carbon\Carbon;

class EmployeeShiftController extends Controller
{
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        return auth()->user()->company_id;
    }

    // =====================
    // LIST SHIFT SAYA
    // =====================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $query = ShiftAssignment::with('shift')
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderBy('date', 'asc');

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        return response()->json([
            'status' => true,
            'message' => 'List shift saya',
            'data' => $query->paginate(20),
        ]);
    }

    // =====================
    // SHIFT HARI INI
    // =====================
    public function today()
    {
        $this->ensureEmployee();

        $today = Carbon::today()->toDateString();

        $assignment = ShiftAssignment::with('shift')
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->first();

        return response()->json([
            'status' => true,
            'message' => 'Shift hari ini',
            'data' => $assignment,
        ]);
    }

    public function range(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $data = ShiftAssignment::query()
            ->with('shift')
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->whereDate('date', '>=', $validated['from'])
            ->whereDate('date', '<=', $validated['to'])
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Range shift saya',
            'data' => $data,
        ]);
    }
}
