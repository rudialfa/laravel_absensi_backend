<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PesantrenSchedulesController extends Controller
{

    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus ustadz)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan pada akun ustadz',
            ], 422));
        }
        return $companyId;
    }

    // private function baseQuery(Request $request)
    private function baseQuery()
    {

        return Schedule::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id());
    }

    // =====================
    // LIST SCHEDULE (USTADZ)
    // =====================
    public function index(Request $request)
    {

        $this->ensureUstadz();

        $query = $this->baseQuery()->orderBy('start_datetime', 'asc');

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
    public function today(Request $request)
    {

        $this->ensureUstadz();

        $today = Carbon::today();

        $data = $this->baseQuery()
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
        $this->ensureUstadz();

        $validated = $request->validate([
            'title' => 'required|string|max:120',
            'description' => 'nullable|string',
            'start_datetime' => 'required|date',
            'reminder_offsets' => 'nullable|array',
            'reminder_offsets.*' => 'integer|min:0',
            'is_task_duty' => 'nullable|boolean',
            'location' => 'nullable|array',
            'status' => 'nullable|in:upcoming,done,canceled',
        ]);

        $schedule = Schedule::create([
            'company_id' => $this->companyId(), // ✅ WAJIB
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start_datetime' => $validated['start_datetime'],
            'reminder_offsets' => $validated['reminder_offsets'] ?? null,
            'is_task_duty' => $validated['is_task_duty'] ?? false,
            'location' => $validated['location'] ?? null,
            'status' => 'upcoming',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Jadwal berhasil dibuat',
            'data' => $this->formatSchedule($schedule),
        ], 201);
    }

    // =====================
    // DETAIL
    // =====================
    public function show(Request $request, $id)
    {

        $this->ensureUstadz();

        $schedule = $this->baseQuery()->findOrFail($id);

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

        $this->ensureUstadz();

        $schedule = $this->baseQuery()->findOrFail($id);

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
    public function destroy(Request $request, $id)
    {

        $this->ensureUstadz();

        $schedule = $this->baseQuery()->findOrFail($id);
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

        $this->ensureUstadz();

        $schedule = $this->baseQuery()->findOrFail($id); // ✅ fix disini

        $request->validate([
            'status' => 'required|in:upcoming,done,canceled'
        ]);

        $schedule->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Status jadwal diperbarui',
            'data' => $this->formatSchedule($schedule),
        ]);
    }

    // =====================
    // HELPER FORMAT RESPONSE
    // =====================
    private function formatSchedule(Schedule $s): array
    {


        $start = Carbon::parse($s->start_datetime);
        $end = (clone $start)->addMinutes(90);

        return [
            'id' => $s->id,
            'company_id' => $s->company_id ?? null,
            'title' => $s->title,
            'description' => $s->description,
            'date' => $start->format('Y-m-d'),
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'start_datetime' => $start->toDateTimeString(),
            'status' => $s->status,
            'is_task_duty' => (bool) $s->is_task_duty,
            'reminder_offsets' => $s->reminder_offsets,
            'location' => $s->location,
        ];
    }
}
