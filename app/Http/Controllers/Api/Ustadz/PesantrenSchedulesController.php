<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PesantrenSchedulesController extends Controller
{
    // =====================
    // LIST SCHEDULE (USTADZ)
    // =====================
    public function index(Request $request)
    {
        $query = Schedule::where('user_id', auth()->id())
            ->orderBy('start_datetime', 'asc');

        // optional filter: status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $data = $query->get()->map(fn($s) => $this->formatSchedule($s));

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // =====================
    // TODAY SCHEDULE
    // =====================
    public function today()
    {
        $today = Carbon::today();

        $data = Schedule::where('user_id', auth()->id())
            ->whereDate('start_datetime', $today)
            ->orderBy('start_datetime')
            ->get()
            ->map(fn($s) => $this->formatSchedule($s));

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // =====================
    // CREATE
    // =====================
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'reminder_offsets' => 'nullable|array',
            'is_task_duty' => 'nullable|boolean',
            'location' => 'nullable|array',
        ]);

        $schedule = Schedule::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'start_datetime' => $request->start_datetime,
            'reminder_offsets' => $request->reminder_offsets,
            'is_task_duty' => $request->is_task_duty ?? false,
            'location' => $request->location,
            'status' => 'upcoming',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil dibuat',
            'data' => $this->formatSchedule($schedule)
        ], 201);
    }

    // =====================
    // DETAIL
    // =====================
    public function show($id)
    {
        $schedule = Schedule::where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $this->formatSchedule($schedule)
        ]);
    }

    // =====================
    // UPDATE
    // =====================
    public function update(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', auth()->id())
            ->findOrFail($id);

        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'reminder_offsets' => 'nullable|array',
            'is_task_duty' => 'nullable|boolean',
            'location' => 'nullable|array',
        ]);

        $schedule->update([
            'title' => $request->title,
            'description' => $request->description,
            'start_datetime' => $request->start_datetime,
            'reminder_offsets' => $request->reminder_offsets,
            'is_task_duty' => $request->is_task_duty ?? false,
            'location' => $request->location,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil diupdate',
            'data' => $this->formatSchedule($schedule)
        ]);
    }

    // =====================
    // DELETE
    // =====================
    public function destroy($id)
    {
        $schedule = Schedule::where('user_id', auth()->id())
            ->findOrFail($id);

        $schedule->delete();

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil dihapus'
        ]);
    }

    // =====================
    // UPDATE STATUS
    // =====================
    public function updateStatus(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', auth()->id())
            ->findOrFail($id);

        $request->validate([
            'status' => 'required|in:upcoming,done,canceled'
        ]);

        $schedule->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status jadwal diperbarui'
        ]);
    }

    // =====================
    // HELPER FORMAT RESPONSE (UI Friendly)
    // =====================
    private function formatSchedule(Schedule $s)
    {
        $start = Carbon::parse($s->start_datetime);

        // default durasi 90 menit biar cocok UI (bisa kamu ubah)
        $end = (clone $start)->addMinutes(90);

        return [
            'id' => $s->id,
            'title' => $s->title,
            'description' => $s->description,
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'start_datetime' => $start->toDateTimeString(),
            'status' => $s->status,
            'is_task_duty' => (bool) $s->is_task_duty,
            'reminder_offsets' => $s->reminder_offsets,
            'location' => $s->location, // json
        ];
    }
}
