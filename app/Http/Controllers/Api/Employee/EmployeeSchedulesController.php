<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Schedule;
use Carbon\Carbon;

class EmployeeSchedulesController extends Controller
{
    // private function ensureEmployee()
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'employee') {
    //         abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus employee)'], 403));
    //     }
    // }

    // private function companyId()
    // {
    //     return auth()->user()->company_id ?? null;
    // }

    // public function index(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $q = Schedule::where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id());

    //     if ($request->filled('from')) $q->whereDate('date', '>=', $request->from);
    //     if ($request->filled('to')) $q->whereDate('date', '<=', $request->to);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List schedule saya',
    //         'data' => $q->orderByDesc('date')->paginate(20),
    //     ]);
    // }

    // public function today()
    // {
    //     $this->ensureEmployee();
    //     $today = Carbon::today()->toDateString();

    //     $data = Schedule::where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->whereDate('date', $today)
    //         ->orderBy('start_time')
    //         ->get();

    //     return response()->json(['status' => true, 'message' => 'Schedule hari ini', 'data' => $data]);
    // }

    // public function store(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $validated = $request->validate([
    //         'title' => 'required|string|max:120',
    //         'date' => 'required|date',
    //         'start_time' => 'required|date_format:H:i',
    //         'end_time' => 'required|date_format:H:i',
    //         'location' => 'nullable|string|max:200',
    //         'notes' => 'nullable|string',
    //     ]);

    //     $schedule = Schedule::create([
    //         'company_id' => $this->companyId(),
    //         'user_id' => auth()->id(),
    //         'title' => $validated['title'],
    //         'date' => $validated['date'],
    //         'start_time' => $validated['start_time'],
    //         'end_time' => $validated['end_time'],
    //         'location' => $validated['location'] ?? null,
    //         'notes' => $validated['notes'] ?? null,
    //         'status' => 'upcoming', // upcoming/ongoing/completed (sesuaikan)
    //     ]);

    //     return response()->json(['status' => true, 'message' => 'Schedule dibuat', 'data' => $schedule], 201);
    // }

    // public function show($id)
    // {
    //     $this->ensureEmployee();

    //     $schedule = Schedule::where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     return response()->json(['status' => true, 'message' => 'Detail schedule', 'data' => $schedule]);
    // }

    // public function update(Request $request, $id)
    // {
    //     $this->ensureEmployee();

    //     $schedule = Schedule::where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     $validated = $request->validate([
    //         'title' => 'sometimes|required|string|max:120',
    //         'date' => 'sometimes|required|date',
    //         'start_time' => 'sometimes|required|date_format:H:i',
    //         'end_time' => 'sometimes|required|date_format:H:i',
    //         'location' => 'nullable|string|max:200',
    //         'notes' => 'nullable|string',
    //     ]);

    //     foreach (['title', 'date', 'start_time', 'end_time', 'location', 'notes'] as $f) {
    //         if (array_key_exists($f, $validated)) $schedule->$f = $validated[$f];
    //     }

    //     $schedule->save();

    //     return response()->json(['status' => true, 'message' => 'Schedule diupdate', 'data' => $schedule]);
    // }

    // public function destroy($id)
    // {
    //     $this->ensureEmployee();

    //     $schedule = Schedule::where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     $schedule->delete();

    //     return response()->json(['status' => true, 'message' => 'Schedule dihapus']);
    // }

    // public function updateStatus(Request $request, $id)
    // {
    //     $this->ensureEmployee();

    //     $schedule = Schedule::where('company_id', $this->companyId())
    //         ->where('user_id', auth()->id())
    //         ->findOrFail($id);

    //     $validated = $request->validate([
    //         'status' => 'required|string|max:30', // upcoming/ongoing/completed
    //     ]);

    //     $schedule->status = $validated['status'];
    //     $schedule->save();

    //     return response()->json(['status' => true, 'message' => 'Status schedule diupdate', 'data' => $schedule]);
    // }

    // kode 2
    // =========================
    // GUARD: pastikan employee
    // =========================
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    // =========================
    // Helper: company_id wajib ada
    // =========================
    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan',
            ], 422));
        }

        return (int) $companyId;
    }

    // =========================
    // BASE QUERY (aman)
    // =========================
    private function baseQuery()
    {
        $this->ensureEmployee();

        return Schedule::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id());
    }

    // =====================
    // LIST (paginate)
    // GET /employee/schedules?status=upcoming|done|canceled&from=YYYY-MM-DD&to=YYYY-MM-DD&page=1
    // =====================
    public function index(Request $request)
    {
        $q = $this->baseQuery()->orderBy('start_datetime', 'asc');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        // filter tanggal by start_datetime
        if ($request->filled('from')) {
            $q->whereDate('start_datetime', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('start_datetime', '<=', $request->to);
        }

        $data = $q->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'List schedule employee',
            'data' => $data,
        ]);
    }

    // =====================
    // TODAY
    // GET /employee/schedules/today
    // =====================
    public function today()
    {
        $today = Carbon::today()->toDateString();

        $data = $this->baseQuery()
            ->whereDate('start_datetime', $today)
            ->orderBy('start_datetime', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Schedule hari ini',
            'data' => $data,
        ]);
    }

    // =====================
    // CREATE
    // POST /employee/schedules
    // body: title, description?, start_datetime, reminder_offsets?, is_task_duty?, location?
    // =====================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'title'            => 'required|string|max:150',
            'description'      => 'nullable|string',
            'start_datetime'   => 'required|date',
            'reminder_offsets' => 'nullable|array',
            'reminder_offsets.*' => 'integer',
            'is_task_duty'     => 'nullable|boolean',
            'location'         => 'nullable|array',
            'status'           => 'nullable|in:upcoming,done,canceled',
        ]);

        $schedule = Schedule::create([
            'company_id'       => $this->companyId(),     // ✅ penting
            'user_id'          => auth()->id(),           // ✅ penting
            'title'            => $validated['title'],
            'description'      => $validated['description'] ?? null,
            'start_datetime'   => $validated['start_datetime'],
            'reminder_offsets' => $validated['reminder_offsets'] ?? null,
            'is_task_duty'     => $validated['is_task_duty'] ?? false,
            'location'         => $validated['location'] ?? null,
            'status'           => $validated['status'] ?? 'upcoming',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil dibuat',
            'data' => $schedule,
        ], 201);
    }

    // =====================
    // DETAIL
    // GET /employee/schedules/{id}
    // =====================
    public function show($id)
    {
        $schedule = $this->baseQuery()->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail schedule',
            'data' => $schedule,
        ]);
    }

    // =====================
    // UPDATE
    // PUT /employee/schedules/{id}
    // =====================
    public function update(Request $request, $id)
    {
        $schedule = $this->baseQuery()->findOrFail($id);

        $validated = $request->validate([
            'title'            => 'sometimes|required|string|max:150',
            'description'      => 'sometimes|nullable|string',
            'start_datetime'   => 'sometimes|required|date',
            'reminder_offsets' => 'sometimes|nullable|array',
            'reminder_offsets.*' => 'integer',
            'is_task_duty'     => 'sometimes|nullable|boolean',
            'location'         => 'sometimes|nullable|array',
            'status'           => 'sometimes|required|in:upcoming,done,canceled',
        ]);

        $schedule->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil diupdate',
            'data' => $schedule->fresh(),
        ]);
    }

    // =====================
    // DELETE
    // DELETE /employee/schedules/{id}
    // =====================
    public function destroy($id)
    {
        $schedule = $this->baseQuery()->findOrFail($id);
        $schedule->delete();

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil dihapus',
        ]);
    }

    // =====================
    // UPDATE STATUS
    // POST /employee/schedules/{id}/status
    // body: status (upcoming/done/canceled)
    // =====================
    public function updateStatus(Request $request, $id)
    {
        $schedule = $this->baseQuery()->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:upcoming,done,canceled',
        ]);

        $schedule->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status jadwal diperbarui',
            'data' => $schedule->fresh(),
        ]);
    }
}
