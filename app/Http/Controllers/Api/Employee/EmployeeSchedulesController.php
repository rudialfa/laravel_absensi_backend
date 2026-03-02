<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSchedulesController extends Controller
{


    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $scope  = $request->scope ?? 'all';

        $query = Schedule::with(['user', 'creator', 'participants.user'])
            ->when($request->status,     fn($q) => $q->where('status', $request->status))
            ->when($request->type,       fn($q) => $q->where('type', $request->type))
            ->when($request->date,       fn($q) => $q->whereDate('start_datetime', $request->date))
            ->when(
                $request->start_date && $request->end_date,
                fn($q) => $q->whereBetween('start_datetime', [
                    $request->start_date,
                    $request->end_date,
                ])
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

        $schedules = $query->paginate($request->per_page ?? 15);

        // Inject info partisipasi saya di setiap jadwal
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
            'success' => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    /**
     * GET /employee/schedules/{id}
     * Detail jadwal (hanya yang melibatkan saya)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

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
            'success' => true,
            'message' => 'Detail jadwal berhasil diambil',
            'data'    => $schedule,
        ]);
    }

    /**
     * GET /employee/schedules/invitations
     * List undangan dimana saya sebagai peserta
     * Filter: status (invited / accepted / declined)
     */
    public function invitations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $items = ScheduleParticipant::with(['schedule.user', 'schedule.creator'])
            ->where('user_id', $userId)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->whereHas('schedule', fn($q) => $q->whereNull('deleted_at'))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Data undangan berhasil diambil',
            'data'    => $items,
        ]);
    }

    /**
     * POST /employee/schedules/{id}/respond
     * Accept / decline undangan
     * Body: { status: 'accepted'|'declined', note: '...' }
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

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
            'success' => true,
            'message' => $request->status === 'accepted'
                ? 'Undangan berhasil diterima'
                : 'Undangan berhasil ditolak',
            'data'    => $participant->load('schedule'),
        ]);
    }
}
