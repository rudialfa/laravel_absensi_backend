<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use Carbon\Carbon;

class EmployeeSchedulesController extends Controller
{
    private function ensureEmployee()
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus employee)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureEmployee();

        $q = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id());

        if ($request->filled('from')) $q->whereDate('date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('date', '<=', $request->to);

        return response()->json([
            'status' => true,
            'message' => 'List schedule saya',
            'data' => $q->orderByDesc('date')->paginate(20),
        ]);
    }

    public function today()
    {
        $this->ensureEmployee();
        $today = Carbon::today()->toDateString();

        $data = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->get();

        return response()->json(['status' => true, 'message' => 'Schedule hari ini', 'data' => $data]);
    }

    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ]);

        $schedule = Schedule::create([
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'upcoming', // upcoming/ongoing/completed (sesuaikan)
        ]);

        return response()->json(['status' => true, 'message' => 'Schedule dibuat', 'data' => $schedule], 201);
    }

    public function show($id)
    {
        $this->ensureEmployee();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail schedule', 'data' => $schedule]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureEmployee();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:120',
            'date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'location' => 'nullable|string|max:200',
            'notes' => 'nullable|string',
        ]);

        foreach (['title', 'date', 'start_time', 'end_time', 'location', 'notes'] as $f) {
            if (array_key_exists($f, $validated)) $schedule->$f = $validated[$f];
        }

        $schedule->save();

        return response()->json(['status' => true, 'message' => 'Schedule diupdate', 'data' => $schedule]);
    }

    public function destroy($id)
    {
        $this->ensureEmployee();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $schedule->delete();

        return response()->json(['status' => true, 'message' => 'Schedule dihapus']);
    }

    public function updateStatus(Request $request, $id)
    {
        $this->ensureEmployee();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|max:30', // upcoming/ongoing/completed
        ]);

        $schedule->status = $validated['status'];
        $schedule->save();

        return response()->json(['status' => true, 'message' => 'Status schedule diupdate', 'data' => $schedule]);
    }
}
