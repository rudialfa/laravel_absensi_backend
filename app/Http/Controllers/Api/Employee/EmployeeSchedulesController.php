<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSchedulesController extends Controller
{
    // Jangan load user utuh (face_embedding bisa puluhan KB per user)
    private const USER_COLS = 'id,name,position,department,image_url';

    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'success' => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function relations(): array
    {
        return [
            'user:' . self::USER_COLS,
            'creator:' . self::USER_COLS,
            'participants.user:' . self::USER_COLS,
        ];
    }

    /** Jadwal milik saya ATAU saya diundang, di company saya */
    private function involvingMe(int $userId, int $companyId)
    {
        return Schedule::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas('participants', fn($q2) => $q2->where('user_id', $userId));
            });
    }

    // ============================================================
    // GET /api/company/employee/schedules
    // filter: scope (all|own|invited), status, type, date,
    //         start_date, end_date, upcoming=1, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $user      = $request->user();
        $userId    = (int) $user->id;
        $companyId = (int) $user->company_id;
        $scope     = $request->scope ?? 'all';

        $query = Schedule::with($this->relations())
            ->where('company_id', $companyId)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type,   fn($q) => $q->where('type', $request->type))
            ->filterDates($request)
            ->when($request->boolean('upcoming'), fn($q) => $q->upcoming())
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

        $schedules = $query->paginate((int) ($request->per_page ?? 15));
        $schedules->getCollection()->transform(fn($s) => $s->toApiArray($userId));

        return response()->json([
            'success' => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/schedules/{id}
    // ============================================================
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureEmployee();

        $user = $request->user();

        $schedule = $this->involvingMe((int) $user->id, (int) $user->company_id)
            ->with($this->relations())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail jadwal berhasil diambil',
            'data'    => $schedule->toApiArray((int) $user->id),
        ]);
    }

    // ============================================================
    // GET /api/company/employee/schedules/invitations
    // filter: status (invited|accepted|declined), per_page
    // ============================================================
    public function invitations(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $user   = $request->user();
        $userId = (int) $user->id;

        $items = ScheduleParticipant::query()
            ->where('user_id', $userId)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->whereHas('schedule', fn($q) => $q->where('company_id', $user->company_id))
            ->with(['schedule' => fn($q) => $q->with($this->relations())])
            ->orderByDesc('created_at')
            ->paginate((int) ($request->per_page ?? 15));

        $items->getCollection()->transform(fn($p) => [
            'id'           => $p->id,
            'schedule_id'  => $p->schedule_id,
            'status'       => $p->status,
            'note'         => $p->note,
            'responded_at' => $p->responded_at
                ? \Carbon\Carbon::parse($p->responded_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
                : null,
            'schedule'     => $p->schedule?->toApiArray($userId),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data undangan berhasil diambil',
            'data'    => $items,
        ]);
    }

    // ============================================================
    // POST /api/company/employee/schedules/{id}/respond
    // body: { status: accepted|declined, note }
    // note WAJIB kalau declined
    // ============================================================
    public function respond(Request $request, int $id): JsonResponse
    {
        $this->ensureEmployee();

        $user = $request->user();

        $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
            'note'   => ['nullable', 'required_if:status,declined', 'string', 'max:500'],
        ], [
            'note.required_if' => 'Alasan wajib diisi kalau menolak undangan.',
        ]);

        $schedule = Schedule::where('company_id', $user->company_id)->findOrFail($id);

        if ($schedule->status === 'canceled') {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini sudah dibatalkan.',
            ], 422);
        }

        if ($schedule->time_status === 'past') {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal ini sudah lewat.',
            ], 422);
        }

        $participant = ScheduleParticipant::where('schedule_id', $schedule->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $participant->update([
            'status'       => $request->status,
            'note'         => $request->note,
            'responded_at' => now(),
        ]);

        $schedule->load($this->relations());

        return response()->json([
            'success' => true,
            'message' => $request->status === 'accepted'
                ? 'Undangan berhasil diterima'
                : 'Undangan berhasil ditolak',
            'data'    => $schedule->toApiArray((int) $user->id),
        ]);
    }
}
