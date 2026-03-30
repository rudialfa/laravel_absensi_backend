<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SantriSchedulesController extends Controller
{

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Santri)',
            ], 403));
        }
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri/schedules
    // Sejajar: EmployeeSchedulesController::index()
    // Query: scope (own|invited|all), status, type, date,
    //        start_date, end_date, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $userId = auth()->id();
        $scope  = $request->get('scope', 'all');

        $query = Schedule::with(['user', 'creator', 'participants.user'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'),   fn($q) => $q->where('type', $request->type))
            ->when($request->filled('date'),   fn($q) => $q->whereDate('start_datetime', $request->date))
            ->when(
                $request->filled('start_date') && $request->filled('end_date'),
                fn($q) => $q->whereBetween('start_datetime', [$request->start_date, $request->end_date])
            )
            ->orderBy('start_datetime', 'asc');

        if ($scope === 'own') {
            $query->where('user_id', $userId);
        } elseif ($scope === 'invited') {
            $query->whereHas('participants', fn($q) => $q->where('user_id', $userId));
        } else {
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas('participants', fn($q2) => $q2->where('user_id', $userId));
            });
        }

        $schedules = $query->paginate((int) $request->get('per_page', 15));

        // Inject info partisipasi santri di setiap jadwal
        $schedules->getCollection()->transform(function ($schedule) use ($userId) {
            $participant = $schedule->participants->firstWhere('user_id', $userId);
            $schedule->my_participation = $participant ? [
                'id'           => $participant->id,
                'status'       => $participant->status,
                'note'         => $participant->note,
                'responded_at' => $participant->responded_at,
            ] : null;
            return $schedule;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    // ============================================================
    // TODAY — GET /api/pesantren/santri/schedules/today
    // Jadwal hari ini yang melibatkan santri ini
    // ============================================================
    public function today(): JsonResponse
    {
        $this->ensureSantri();

        $userId = auth()->id();

        $schedules = Schedule::with(['user', 'creator', 'participants.user'])
            ->whereDate('start_datetime', today())
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas('participants', fn($q2) => $q2->where('user_id', $userId));
            })
            ->orderBy('start_datetime', 'asc')
            ->get()
            ->map(function ($schedule) use ($userId) {
                $participant = $schedule->participants->firstWhere('user_id', $userId);
                $schedule->my_participation = $participant ? [
                    'id'           => $participant->id,
                    'status'       => $participant->status,
                    'note'         => $participant->note,
                    'responded_at' => $participant->responded_at,
                ] : null;
                return $schedule;
            });

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal hari ini',
            'data'    => [
                'date'      => today()->toDateString(),
                'total'     => $schedules->count(),
                'schedules' => $schedules,
            ],
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/schedules/{id}
    // Sejajar: EmployeeSchedulesController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $userId = auth()->id();

        $schedule = Schedule::with(['user', 'creator', 'participants.user'])
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas('participants', fn($q2) => $q2->where('user_id', $userId));
            })
            ->findOrFail($id);

        $participant = $schedule->participants->firstWhere('user_id', $userId);
        $schedule->my_participation = $participant ? [
            'id'           => $participant->id,
            'status'       => $participant->status,
            'note'         => $participant->note,
            'responded_at' => $participant->responded_at,
        ] : null;

        return response()->json([
            'status'  => true,
            'message' => 'Detail jadwal berhasil diambil',
            'data'    => $schedule,
        ]);
    }

    // ============================================================
    // INVITATIONS — GET /api/pesantren/santri/schedules/invitations
    // Sejajar: EmployeeSchedulesController::invitations()
    // Query: status (invited|accepted|declined), per_page
    // ============================================================
    public function invitations(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $userId = auth()->id();

        $items = ScheduleParticipant::with(['schedule.user', 'schedule.creator'])
            ->where('user_id', $userId)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->whereHas('schedule', fn($q) => $q->whereNull('deleted_at'))
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Data undangan berhasil diambil',
            'data'    => $items,
        ]);
    }

    // ============================================================
    // RESPOND — POST /api/pesantren/santri/schedules/{id}/respond
    // Sejajar: EmployeeSchedulesController::respond()
    // Body: status (accepted|declined), note
    // ============================================================
    public function respond(Request $request, int $id): JsonResponse
    {
        $this->ensureSantri();

        $userId = auth()->id();

        $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $participant = ScheduleParticipant::where('schedule_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $participant->update([
            'status'       => $request->status,
            'note'         => $request->note,
            'responded_at' => now(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => $request->status === 'accepted'
                ? 'Undangan berhasil diterima'
                : 'Undangan berhasil ditolak',
            'data'    => $participant->load('schedule'),
        ]);
    }
}
