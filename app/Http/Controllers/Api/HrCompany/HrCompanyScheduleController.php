<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\ScheduleParticipant;
use App\Services\Notifiers\ScheduleNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrCompanyScheduleController extends Controller
{
    private const USER_COLS = 'id,name,position,department,image_url';
    private const TYPES     = ['meeting', 'task_duty', 'visit', 'training', 'other'];
    private const STATUSES  = ['upcoming', 'done', 'canceled'];

    public function __construct(private ScheduleNotifier $scheduleNotifier) {}

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    // Sebelumnya tidak ada cek role
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['success' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function relations(): array
    {
        return [
            'user:' . self::USER_COLS,
            'creator:' . self::USER_COLS,
            'participants.user:' . self::USER_COLS,
        ];
    }

    /**
     * Sebelumnya 'exists:users,id' → HR bisa memasukkan user dari COMPANY LAIN.
     * Sekarang user harus satu company.
     */
    private function companyUserRule()
    {
        return Rule::exists('users', 'id')->where('company_id', $this->companyId());
    }

    private function findOrFail(int $id): Schedule
    {
        return Schedule::where('company_id', $this->companyId())->findOrFail($id);
    }

    private const MESSAGES = [
        'start_datetime.required'       => 'Waktu mulai wajib diisi.',
        'start_datetime.after_or_equal' => 'Waktu mulai tidak boleh di masa lalu.',
        'user_id.exists'                => 'Karyawan tidak ditemukan di perusahaan ini.',
        'participants.*.exists'         => 'Ada peserta yang tidak terdaftar di perusahaan ini.',
    ];

    // ============================================================
    // GET /api/company/hr/schedules
    // filter: status, type, user_id, date, start_date, end_date, q, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureHr();

        $schedules = Schedule::with($this->relations())
            ->where('company_id', $this->companyId())
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->when($request->type,    fn($q) => $q->where('type', $request->type))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->q,       fn($q) => $q->where('title', 'like', '%' . $request->q . '%'))
            ->filterDates($request)
            ->orderBy('start_datetime', 'asc')
            ->paginate((int) ($request->per_page ?? 15));

        $schedules->getCollection()->transform(fn($s) => $s->toApiArray());

        return response()->json([
            'success' => true,
            'message' => 'Data jadwal berhasil diambil',
            'data'    => $schedules,
        ]);
    }

    // ============================================================
    // POST /api/company/hr/schedules
    //
    // Format waktu yang diterima (semua dianggap / dikonversi ke WIB):
    //   "2026-10-25 09:00"            → 09:00 WIB
    //   "2026-10-25T09:00:00+07:00"   → 09:00 WIB
    //   "2026-10-25T02:00:00.000Z"    → 09:00 WIB (dikonversi dari UTC)
    // ============================================================
    public function store(Request $request): JsonResponse
    {
        $this->ensureHr();

        $request->validate([
            'user_id'             => ['required', $this->companyUserRule()],
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'type'                => ['required', Rule::in(self::TYPES)],
            'start_datetime'      => ['required', 'date'],
            'end_datetime'        => ['nullable', 'date'],
            'reminder_offsets'    => ['nullable', 'array'],
            'reminder_offsets.*'  => ['integer', 'min:1'],
            'location'            => ['nullable', 'array'],
            'is_recurring'        => ['nullable', 'boolean'],
            'recurrence_type'     => [
                'nullable',
                Rule::requiredIf($request->boolean('is_recurring')),
                Rule::in(['daily', 'weekly', 'monthly']),
            ],
            'recurrence_end_date' => [
                'nullable',
                Rule::requiredIf($request->boolean('is_recurring')),
                'date',
            ],
            'status'              => ['nullable', Rule::in(self::STATUSES)],
            'participants'        => ['nullable', 'array'],
            'participants.*'      => ['integer', $this->companyUserRule()],
        ], self::MESSAGES);

        // Validasi waktu SETELAH dikonversi ke WIB
        // (validasi bawaan "after:now" membandingkan string mentah, bisa meleset 7 jam)
        $start = Carbon::parse(Schedule::toLocal($request->start_datetime));
        $end   = $request->filled('end_datetime') ? Carbon::parse(Schedule::toLocal($request->end_datetime)) : null;

        if ($start->lt(now()->subMinutes(5))) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu mulai tidak boleh di masa lalu.',
            ], 422);
        }

        if ($end && $end->lte($start)) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus setelah waktu mulai.',
            ], 422);
        }

        if (
            $request->boolean('is_recurring')
            && Carbon::parse($request->recurrence_end_date)->endOfDay()->lte($start)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal akhir pengulangan harus setelah waktu mulai.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            $schedule = Schedule::create([
                'company_id'          => $this->companyId(),
                'user_id'             => $request->user_id,
                'created_by'          => $request->user()->id,
                'title'               => $request->title,
                'description'         => $request->description,
                'type'                => $request->type,
                'start_datetime'      => $request->start_datetime, // dikonversi di model
                'end_datetime'        => $request->end_datetime,
                'reminder_offsets'    => $request->reminder_offsets,
                'location'            => $request->location,
                'is_recurring'        => $request->boolean('is_recurring'),
                'recurrence_type'     => $request->boolean('is_recurring') ? $request->recurrence_type : null,
                'recurrence_end_date' => $request->boolean('is_recurring') ? $request->recurrence_end_date : null,
                'status'              => $request->status ?? 'upcoming',
            ]);

            if ($request->filled('participants')) {
                $rows = collect($request->participants)
                    ->unique()
                    ->reject(fn($uid) => (int) $uid === (int) $request->user_id) // pemilik tidak perlu diundang
                    ->map(fn($uid) => [
                        'schedule_id' => $schedule->id,
                        'user_id'     => $uid,
                        'status'      => 'invited',
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ])->values()->toArray();

                if ($rows) {
                    ScheduleParticipant::insert($rows);
                }
            }

            DB::commit();

            // 🔔 Notifikasi ke pemilik jadwal + peserta
            $this->scheduleNotifier->created(
                $schedule,
                isset($rows) ? collect($rows)->pluck('user_id')->all() : []
            );
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat jadwal',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dibuat',
            'data'    => $schedule->load($this->relations())->toApiArray(),
        ], 201);
    }

    // ============================================================
    // GET /api/company/hr/schedules/{id}
    // ============================================================
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureHr();

        $schedule = Schedule::with($this->relations())
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail jadwal berhasil diambil',
            'data'    => $schedule->toApiArray(),
        ]);
    }

    // ============================================================
    // PUT /api/company/hr/schedules/{id}
    // ============================================================
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureHr();

        $schedule = $this->findOrFail($id);

        $request->validate([
            'user_id'             => ['sometimes', $this->companyUserRule()],
            'title'               => ['sometimes', 'required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'type'                => ['sometimes', Rule::in(self::TYPES)],
            'start_datetime'      => ['sometimes', 'required', 'date'],
            'end_datetime'        => ['nullable', 'date'],
            'reminder_offsets'    => ['nullable', 'array'],
            'reminder_offsets.*'  => ['integer', 'min:1'],
            'location'            => ['nullable', 'array'],
            'is_recurring'        => ['sometimes', 'boolean'],
            'recurrence_type'     => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_end_date' => ['nullable', 'date'],
            'status'              => ['sometimes', Rule::in(self::STATUSES)],
        ], self::MESSAGES);

        // Sebelumnya: kirim end_datetime saja → validasi "after:start_datetime"
        // gagal karena start_datetime tidak ikut dikirim. Sekarang dibandingkan
        // dengan nilai lama kalau tidak dikirim.
        $start = $request->has('start_datetime')
            ? Carbon::parse(Schedule::toLocal($request->start_datetime))
            : $schedule->start_datetime;

        $endRaw = $request->has('end_datetime') ? $request->end_datetime : $schedule->end_datetime;
        $end    = $endRaw ? Carbon::parse(Schedule::toLocal($endRaw)) : null;

        if ($end && $start && $end->lte($start)) {
            return response()->json([
                'success' => false,
                'message' => 'Waktu selesai harus setelah waktu mulai.',
            ], 422);
        }

        $data = $request->only([
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
        ]);

        if (array_key_exists('is_recurring', $data) && !$request->boolean('is_recurring')) {
            $data['recurrence_type']     = null;
            $data['recurrence_end_date'] = null;
        }

        // Simpan nilai lama untuk menentukan perlu notifikasi atau tidak
        $old = [
            'title'          => $schedule->title,
            'start_datetime' => $schedule->start_datetime?->format('Y-m-d H:i'),
            'end_datetime'   => $schedule->end_datetime?->format('Y-m-d H:i'),
            'location'       => json_encode($schedule->location),
            'status'         => $schedule->status,
        ];

        $schedule->update($data); // waktu dikonversi ke WIB di model
        $schedule->refresh();

        // 🔔 Notifikasi: dibatalkan, atau judul / waktu / lokasi berubah
        if ($old['status'] !== 'canceled' && $schedule->status === 'canceled') {
            $this->scheduleNotifier->canceled($schedule);
        } elseif (
            $schedule->status === 'upcoming' && (
                $old['title'] !== $schedule->title
                || $old['start_datetime'] !== $schedule->start_datetime?->format('Y-m-d H:i')
                || $old['end_datetime'] !== $schedule->end_datetime?->format('Y-m-d H:i')
                || $old['location'] !== json_encode($schedule->location)
            )
        ) {
            $this->scheduleNotifier->updated($schedule);
        }

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil diupdate',
            'data'    => $schedule->fresh($this->relations())->toApiArray(),
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/schedules/{id}   (soft delete)
    // ============================================================
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->ensureHr();

        $schedule = $this->findOrFail($id);

        // 🔔 Notifikasi pembatalan (sebelum dihapus, supaya daftar peserta masih ada)
        if ($schedule->status === 'upcoming' && $schedule->time_status !== 'past') {
            $this->scheduleNotifier->canceled($schedule);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jadwal berhasil dihapus',
        ]);
    }

    // ============================================================
    // POST /api/company/hr/schedules/{id}/participants
    // body: { participants: [user_id, ...] }
    // ============================================================
    public function addParticipants(Request $request, int $id): JsonResponse
    {
        $this->ensureHr();

        $schedule = $this->findOrFail($id);

        $request->validate([
            'participants'   => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', $this->companyUserRule()],
        ], self::MESSAGES);

        $existing = ScheduleParticipant::where('schedule_id', $schedule->id)
            ->pluck('user_id')
            ->map(fn($v) => (int) $v)
            ->all();

        $added   = [];
        $skipped = [];

        foreach (collect($request->participants)->map(fn($v) => (int) $v)->unique() as $userId) {
            if (in_array($userId, $existing, true) || $userId === (int) $schedule->user_id) {
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

        // 🔔 Undangan ke peserta yang baru ditambahkan
        if ($added) {
            $this->scheduleNotifier->invited($schedule, $added);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peserta berhasil ditambahkan',
            'data'    => [
                'added'    => $added,
                'skipped'  => $skipped,
                'schedule' => $schedule->fresh($this->relations())->toApiArray(),
            ],
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/schedules/{id}/participants/{userId}
    // ============================================================
    public function removeParticipant(Request $request, int $id, int $userId): JsonResponse
    {
        $this->ensureHr();

        $schedule = $this->findOrFail($id);

        ScheduleParticipant::where('schedule_id', $schedule->id)
            ->where('user_id', $userId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Peserta berhasil dihapus dari jadwal',
        ]);
    }

    // ============================================================
    // GET /api/company/hr/schedules/{id}/participants?status=
    // ============================================================
    public function getParticipants(Request $request, int $id): JsonResponse
    {
        $this->ensureHr();

        $schedule = $this->findOrFail($id);

        $participants = ScheduleParticipant::with('user:' . self::USER_COLS)
            ->where('schedule_id', $schedule->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->get()
            ->map(fn($p) => [
                'id'           => $p->id,
                'user_id'      => $p->user_id,
                'status'       => $p->status,
                'note'         => $p->note,
                'responded_at' => $p->responded_at
                    ? Carbon::parse($p->responded_at)->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
                    : null,
                'user'         => $p->user ? [
                    'id'         => $p->user->id,
                    'name'       => $p->user->name,
                    'position'   => $p->user->position,
                    'department' => $p->user->department,
                    'image_url'  => $p->user->image_url ? asset($p->user->image_url) : null,
                ] : null,
            ])->values();

        return response()->json([
            'success' => true,
            'message' => 'Data peserta berhasil diambil',
            'data'    => $participants,
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/schedules/export?status=&type=&user_id=&start_date=&end_date=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $schedules = Schedule::with(['user:id,name,position,department', 'participants'])
            ->where('company_id', $this->companyId())
            ->when($request->status,  fn($q) => $q->where('status', $request->status))
            ->when($request->type,    fn($q) => $q->where('type', $request->type))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->filterDates($request)
            ->orderBy('start_datetime', 'asc')
            ->get();

        $stats = [
            'total'    => $schedules->count(),
            'upcoming' => $schedules->where('status', 'upcoming')->count(),
            'done'     => $schedules->where('status', 'done')->count(),
            'canceled' => $schedules->where('status', 'canceled')->count(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_schedule', [
            'company'     => $request->user()->company ?? (object) ['name' => ''],
            'schedules'   => $schedules,
            'stats'       => $stats,
            'typeLabels'  => Schedule::TYPE_LABELS,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('schedules-' . now()->format('Y-m-d') . '.pdf');
    }
}
