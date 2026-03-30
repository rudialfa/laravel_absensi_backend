<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PesantrenSchedulesController extends Controller
{

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Company ID tidak ditemukan pada akun ustadz',
            ], 422));
        }
        return (int) $companyId;
    }

    // ============================================================
    // INDEX — GET /api/pesantren/schedules
    // Sejajar: HrCompanyScheduleController::index()
    // Query: status, type, user_id (santri_id), date, start_date,
    //        end_date, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $schedules = Schedule::with(['user', 'creator', 'participants.user'])
            ->where('company_id', $this->companyId())
            ->when($request->filled('status'),  fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'),    fn($q) => $q->where('type', $request->type))
            ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('date'),    fn($q) => $q->whereDate('start_datetime', $request->date))
            ->when(
                $request->filled('start_date') && $request->filled('end_date'),
                fn($q) => $q->whereBetween('start_datetime', [$request->start_date, $request->end_date])
            )
            ->orderBy('start_datetime', 'asc')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'status'  => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    // ============================================================
    // TODAY — GET /api/pesantren/schedules/today
    // Jadwal hari ini (harus di atas show agar tidak konflik /{id})
    // ============================================================
    public function today(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $schedules = Schedule::with(['user', 'creator', 'participants.user'])
            ->where('company_id', $this->companyId())
            ->whereDate('start_datetime', today())
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'),   fn($q) => $q->where('type', $request->type))
            ->orderBy('start_datetime', 'asc')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal hari ini berhasil diambil',
            'data'    => [
                'date'      => today()->toDateString(),
                'total'     => $schedules->count(),
                'schedules' => $schedules,
            ],
        ]);
    }

    // ============================================================
    // STORE — POST /api/pesantren/schedules
    // Sejajar: HrCompanyScheduleController::store()
    // ============================================================
    public function store(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $request->validate([
            'user_id'             => ['required', 'exists:users,id'],
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'type'                => ['required', Rule::in(['meeting', 'task_duty', 'visit', 'training', 'other'])],
            'start_datetime'      => ['required', 'date'],
            'end_datetime'        => ['nullable', 'date', 'after:start_datetime'],
            'reminder_offsets'    => ['nullable', 'array'],
            'reminder_offsets.*'  => ['integer', 'min:1'],
            'location'            => ['nullable', 'array'],
            'is_recurring'        => ['boolean'],
            'recurrence_type'     => ['nullable', 'required_if:is_recurring,true', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_end_date' => ['nullable', 'required_if:is_recurring,true', 'date', 'after:start_datetime'],
            'status'              => ['nullable', Rule::in(['upcoming', 'done', 'canceled'])],
            'participants'        => ['nullable', 'array'],
            'participants.*'      => ['integer', 'exists:users,id'],
        ]);

        DB::beginTransaction();

        try {
            $schedule = Schedule::create([
                'company_id'          => $this->companyId(),
                'user_id'             => $request->user_id,
                'created_by'          => auth()->id(),
                'title'               => $request->title,
                'description'         => $request->description,
                'type'                => $request->type,
                'start_datetime'      => $request->start_datetime,
                'end_datetime'        => $request->end_datetime,
                'reminder_offsets'    => $request->reminder_offsets,
                'location'            => $request->location,
                'is_recurring'        => $request->is_recurring ?? false,
                'recurrence_type'     => $request->recurrence_type,
                'recurrence_end_date' => $request->recurrence_end_date,
                'status'              => $request->status ?? 'upcoming',
            ]);

            // Tambah peserta jika ada
            if ($request->filled('participants')) {
                $participants = collect($request->participants)
                    ->unique()
                    ->map(fn($userId) => [
                        'schedule_id' => $schedule->id,
                        'user_id'     => $userId,
                        'status'      => 'invited',
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ])->toArray();

                ScheduleParticipant::insert($participants);
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Jadwal berhasil dibuat',
                'data'    => $schedule->load(['user', 'creator', 'participants.user']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'Gagal membuat jadwal',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // SHOW — GET /api/pesantren/schedules/{id}
    // Sejajar: HrCompanyScheduleController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::with(['user', 'creator', 'participants.user'])
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail jadwal berhasil diambil',
            'data'    => $schedule,
        ]);
    }

    // ============================================================
    // UPDATE — PUT /api/pesantren/schedules/{id}
    // Sejajar: HrCompanyScheduleController::update()
    // ============================================================
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->findOrFail($id);

        $request->validate([
            'user_id'             => ['sometimes', 'exists:users,id'],
            'title'               => ['sometimes', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'type'                => ['sometimes', Rule::in(['meeting', 'task_duty', 'visit', 'training', 'other'])],
            'start_datetime'      => ['sometimes', 'date'],
            'end_datetime'        => ['nullable', 'date', 'after:start_datetime'],
            'reminder_offsets'    => ['nullable', 'array'],
            'reminder_offsets.*'  => ['integer', 'min:1'],
            'location'            => ['nullable', 'array'],
            'is_recurring'        => ['boolean'],
            'recurrence_type'     => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_end_date' => ['nullable', 'date'],
            'status'              => ['sometimes', Rule::in(['upcoming', 'done', 'canceled'])],
        ]);

        $schedule->update($request->only([
            'user_id',
            'title',
            'description',
            'type',
            'start_datetime',
            'end_datetime',
            'reminder_offsets',
            'location',
            'is_recurring',
            'recurrence_type',
            'recurrence_end_date',
            'status',
        ]));

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal berhasil diupdate',
            'data'    => $schedule->load(['user', 'creator', 'participants.user']),
        ]);
    }

    // ============================================================
    // DESTROY — DELETE /api/pesantren/schedules/{id}
    // Sejajar: HrCompanyScheduleController::destroy()
    // ============================================================
    public function destroy(int $id): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->findOrFail($id);

        $schedule->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal berhasil dihapus',
        ]);
    }

    // ============================================================
    // UPDATE STATUS — POST /api/pesantren/schedules/{id}/status
    // (Tambahan khusus pesantren — update status saja tanpa full update)
    // ============================================================
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->findOrFail($id);

        $request->validate([
            'status' => ['required', Rule::in(['upcoming', 'done', 'canceled'])],
        ]);

        $schedule->update(['status' => $request->status]);

        return response()->json([
            'status'  => true,
            'message' => 'Status jadwal berhasil diperbarui',
            'data'    => [
                'id'     => $schedule->id,
                'status' => $schedule->status,
            ],
        ]);
    }

    // ============================================================
    // GET PARTICIPANTS — GET /api/pesantren/schedules/{id}/participants
    // Sejajar: HrCompanyScheduleController::getParticipants()
    // ============================================================
    public function getParticipants(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->findOrFail($id);

        $participants = ScheduleParticipant::with('user')
            ->where('schedule_id', $schedule->id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data peserta berhasil diambil',
            'data'    => $participants,
        ]);
    }

    // ============================================================
    // ADD PARTICIPANTS — POST /api/pesantren/schedules/{id}/participants
    // Sejajar: HrCompanyScheduleController::addParticipants()
    // ============================================================
    public function addParticipants(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->findOrFail($id);

        $request->validate([
            'participants'   => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', 'exists:users,id'],
        ]);

        $added   = [];
        $skipped = [];

        foreach (collect($request->participants)->unique() as $userId) {
            $exists = ScheduleParticipant::where('schedule_id', $schedule->id)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                $skipped[] = $userId;
                continue;
            }

            ScheduleParticipant::create([
                'schedule_id' => $schedule->id,
                'user_id'     => $userId,
                'status'      => 'invited',
            ]);

            $added[] = $userId;
        }

        return response()->json([
            'status'  => true,
            'message' => 'Peserta berhasil ditambahkan',
            'data'    => [
                'added'   => $added,
                'skipped' => $skipped,
            ],
        ]);
    }

    // ============================================================
    // REMOVE PARTICIPANT — DELETE /api/pesantren/schedules/{id}/participants/{santriId}
    // Sejajar: HrCompanyScheduleController::removeParticipant()
    // ============================================================
    public function removeParticipant(int $id, int $santriId): JsonResponse
    {
        $this->ensureUstadz();

        $schedule = Schedule::where('company_id', $this->companyId())
            ->findOrFail($id);

        $participant = ScheduleParticipant::where('schedule_id', $schedule->id)
            ->where('user_id', $santriId)
            ->firstOrFail();

        $participant->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Peserta berhasil dihapus dari jadwal',
        ]);
    }

    // ============================================================
    // EXPORT PDF — GET /api/pesantren/schedules/export
    // Sejajar: HrCompanyScheduleController::export()
    // Query: status, type, user_id, start_date, end_date
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();

        $query = Schedule::with(['user:id,name,position,department', 'participants'])
            ->where('company_id', $this->companyId())
            ->orderBy('start_datetime', 'asc');

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('type'))     $query->where('type', $request->type);
        if ($request->filled('user_id'))  $query->where('user_id', $request->user_id);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('start_datetime', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->whereDate('start_datetime', '>=', $request->start_date);
        }

        $schedules = $query->get();

        $stats = [
            'total'    => $schedules->count(),
            'upcoming' => $schedules->where('status', 'upcoming')->count(),
            'done'     => $schedules->where('status', 'done')->count(),
            'canceled' => $schedules->where('status', 'canceled')->count(),
        ];

        $typeLabels = [
            'meeting'   => 'Meeting',
            'task_duty' => 'Tugas',
            'visit'     => 'Kunjungan',
            'training'  => 'Training',
            'other'     => 'Lainnya',
        ];

        $fileName = 'jadwal-pesantren-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_schedule', [
            'company'     => auth()->user()->company ?? (object)['name' => ''],
            'schedules'   => $schedules,
            'stats'       => $stats,
            'typeLabels'  => $typeLabels,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
