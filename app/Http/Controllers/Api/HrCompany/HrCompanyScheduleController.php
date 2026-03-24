<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrCompanyScheduleController extends Controller
{
     // ==================== SCHEDULE ====================

    /**
     * GET /hr/schedules
     * Ambil semua jadwal milik company HR yang login
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $schedules = Schedule::with(['user', 'creator', 'participants.user'])
            ->where('company_id', $companyId)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->date, fn($q) => $q->whereDate('start_datetime', $request->date))
            ->when(
                $request->start_date && $request->end_date,
                fn($q) =>
                $q->whereBetween('start_datetime', [$request->start_date, $request->end_date])
            )
            ->orderBy('start_datetime', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    /**
     * POST /hr/schedules
     * Buat jadwal baru untuk karyawan
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'              => ['required', 'exists:users,id'],
            'title'                => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'type'                 => ['required', Rule::in(['meeting', 'task_duty', 'visit', 'training', 'other'])],
            'start_datetime'       => ['required', 'date', 'after_or_equal:now'],
            'end_datetime'         => ['nullable', 'date', 'after:start_datetime'],
            'reminder_offsets'     => ['nullable', 'array'],
            'reminder_offsets.*'   => ['integer', 'min:1'],
            'location'             => ['nullable', 'array'],
            'is_recurring'         => ['boolean'],
            'recurrence_type'      => ['nullable', 'required_if:is_recurring,true', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_end_date'  => ['nullable', 'required_if:is_recurring,true', 'date', 'after:start_datetime'],
            'status'               => ['nullable', Rule::in(['upcoming', 'done', 'canceled'])],
            'participants'         => ['nullable', 'array'],
            'participants.*'       => ['integer', 'exists:users,id'],
        ]);

        DB::beginTransaction();

        try {
            $schedule = Schedule::create([
                'company_id'           => $request->user()->company_id,
                'user_id'              => $request->user_id,
                'created_by'           => $request->user()->id,
                'title'                => $request->title,
                'description'          => $request->description,
                'type'                 => $request->type,
                'start_datetime'       => $request->start_datetime,
                'end_datetime'         => $request->end_datetime,
                'reminder_offsets'     => $request->reminder_offsets,
                'location'             => $request->location,
                'is_recurring'         => $request->is_recurring ?? false,
                'recurrence_type'      => $request->recurrence_type,
                'recurrence_end_date'  => $request->recurrence_end_date,
                'status'               => $request->status ?? 'upcoming',
            ]);

            // Tambah peserta jika ada
            if ($request->filled('participants')) {
                $participants = collect($request->participants)
                    ->unique()
                    ->map(fn($userId) => [
                        'schedule_id'  => $schedule->id,
                        'user_id'      => $userId,
                        'status'       => 'invited',
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ])->toArray();

                ScheduleParticipant::insert($participants);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Jadwal berhasil dibuat',
                'data'    => $schedule->load(['user', 'creator', 'participants.user']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /hr/schedules/{id}
     * Detail jadwal
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::with(['user', 'creator', 'participants.user'])
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail jadwal berhasil diambil',
            'data'    => $schedule,
        ]);
    }

    /**
     * PUT /hr/schedules/{id}
     * Update jadwal
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $request->validate([
            'user_id'              => ['sometimes', 'exists:users,id'],
            'title'                => ['sometimes', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'type'                 => ['sometimes', Rule::in(['meeting', 'task_duty', 'visit', 'training', 'other'])],
            'start_datetime'       => ['sometimes', 'date'],
            'end_datetime'         => ['nullable', 'date', 'after:start_datetime'],
            'reminder_offsets'     => ['nullable', 'array'],
            'reminder_offsets.*'   => ['integer', 'min:1'],
            'location'             => ['nullable', 'array'],
            'is_recurring'         => ['boolean'],
            'recurrence_type'      => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_end_date'  => ['nullable', 'date'],
            'status'               => ['sometimes', Rule::in(['upcoming', 'done', 'canceled'])],
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
            'success' => true,
            'message' => 'Jadwal berhasil diupdate',
            'data'    => $schedule->load(['user', 'creator', 'participants.user']),
        ]);
    }

    /**
     * DELETE /hr/schedules/{id}
     * Hapus jadwal (soft delete)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dihapus',
        ]);
    }

    // ==================== PARTICIPANTS ====================

    /**
     * POST /hr/schedules/{id}/participants
     * Tambah peserta ke jadwal
     */
    public function addParticipants(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $request->validate([
            'participants'   => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', 'exists:users,id'],
        ]);

        $added = [];
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
            'success' => true,
            'message' => 'Peserta berhasil ditambahkan',
            'data'    => [
                'added'   => $added,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * DELETE /hr/schedules/{id}/participants/{userId}
     * Hapus peserta dari jadwal
     */
    public function removeParticipant(Request $request, int $id, int $userId): JsonResponse
    {
        $schedule = Schedule::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $participant = ScheduleParticipant::where('schedule_id', $schedule->id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $participant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Peserta berhasil dihapus dari jadwal',
        ]);
    }

    /**
     * GET /hr/schedules/{id}/participants
     * Ambil semua peserta jadwal
     */
    public function getParticipants(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $participants = ScheduleParticipant::with('user')
            ->where('schedule_id', $schedule->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data peserta berhasil diambil',
            'data'    => $participants,
        ]);
    }

    public function export(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = Schedule::with(['user:id,name,position,department', 'participants'])
            ->where('company_id', $companyId)
            ->orderBy('start_datetime', 'asc');

        if ($request->filled('status'))     $query->where('status', $request->status);
        if ($request->filled('type'))       $query->where('type',   $request->type);
        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
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

        $fileName = 'schedules-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_schedule', [
            'company'     => $request->user()->company ?? (object)['name' => ''],
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
