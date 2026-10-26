<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OvertimeRequest;
use App\Services\AttendancePhotoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class EmployeeOvertimeRequestController extends Controller
{
    private const EVIDENCE_FOLDER = 'image/overtime';

    public function __construct(private AttendancePhotoService $photos) {}

    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function attendanceBrief(?Attendance $a): ?array
    {
        if (!$a) return null;

        return [
            'id'            => $a->id,
            'date'          => $a->date?->toDateString(),
            'time_in'       => $a->time_in,
            'time_out'      => $a->time_out,
            'scheduled_out' => $a->scheduled_out,
            'status'        => $a->status,
        ];
    }

    // ============================================================
    // GET /api/company/employee/overtimes
    // filter: status, from, to, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureEmployee();
        $user = $request->user();

        $q = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['approver:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('from'))   $q->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))     $q->whereDate('date', '<=', $request->to);

        $page = $q->paginate((int) $request->get('per_page', 10));
        $page->getCollection()->transform(fn($o) => $o->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'List overtime requests',
            'data'    => $page,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/overtimes/{id}
    // ============================================================
    public function show(Request $request, int $id)
    {
        $this->ensureEmployee();
        $user = $request->user();

        $o = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['attendance', 'approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Overtime request detail',
            'data'    => array_merge($o->toApiArray(), [
                'attendance' => $this->attendanceBrief($o->attendance),
            ]),
        ]);
    }

    // ============================================================
    // POST /api/company/employee/overtimes
    //
    // Bukti bisa dikirim dengan 2 cara:
    //  1. multipart  : evidence_image = file (cara lama, tetap jalan)
    //  2. JSON       : evidence_photo = string base64 (sama seperti foto absen)
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureEmployee();
        $user  = $request->user();
        $today = Carbon::today();

        $validated = $request->validate([
            'date'           => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:' . $today->copy()->subDays(30)->toDateString(),
                'before_or_equal:' . $today->copy()->addDays(30)->toDateString(),
            ],
            'start_time'     => ['required', 'date_format:H:i'],
            'end_time'       => ['required', 'date_format:H:i', 'different:start_time'],
            'reason'         => ['required', 'string', 'min:5', 'max:1000'],
            'attendance_id'  => ['nullable', 'integer'],
            'evidence_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'evidence_photo' => ['nullable', 'string', 'min:100'],
        ], [
            'date.date_format'       => 'Format tanggal harus YYYY-MM-DD.',
            'date.after_or_equal'    => 'Lembur hanya bisa diajukan maksimal 30 hari ke belakang.',
            'date.before_or_equal'   => 'Lembur hanya bisa diajukan maksimal 30 hari ke depan.',
            'end_time.different'     => 'Jam selesai tidak boleh sama dengan jam mulai.',
            'reason.required'        => 'Alasan / uraian pekerjaan lembur wajib diisi.',
            'reason.min'             => 'Alasan lembur minimal 5 karakter.',
        ]);

        // Hitung durasi DI TANGGAL lembur (bukan tanggal hari ini).
        // end <= start → lewat tengah malam, selesai keesokan harinya.
        $date  = $validated['date'];
        $start = Carbon::parse($date . ' ' . $validated['start_time']);
        $end   = Carbon::parse($date . ' ' . $validated['end_time']);
        if ($end->lte($start)) {
            $end->addDay();
        }

        $minutes = (int) $start->diffInMinutes($end, true);

        if ($minutes < 15 || $minutes > 12 * 60) {
            return response()->json([
                'status'  => false,
                'message' => 'Durasi lembur harus antara 15 menit dan 12 jam.',
            ], 422);
        }

        // Satu lembur aktif (pending/approved) per tanggal
        $exists = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status'  => false,
                'message' => 'Kamu sudah punya pengajuan lembur pada tanggal tersebut.',
            ], 409);
        }

        // Absen: pakai attendance_id kalau dikirim, kalau tidak cari otomatis dari tanggal
        $attendance = Attendance::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->when(
                $request->filled('attendance_id'),
                fn($q) => $q->where('id', $request->attendance_id),
                fn($q) => $q->whereDate('date', $date)
            )
            ->first();

        if ($request->filled('attendance_id') && !$attendance) {
            return response()->json([
                'status'  => false,
                'message' => 'attendance_id tidak valid untuk user ini.',
            ], 422);
        }

        if ($attendance && $attendance->date->toDateString() !== $date) {
            return response()->json([
                'status'  => false,
                'message' => 'Tanggal lembur tidak sama dengan tanggal absen yang dipilih.',
            ], 422);
        }

        // Simpan bukti (folder sendiri, sebelumnya tercampur di folder izin)
        $imagePath = null;
        if ($request->hasFile('evidence_image')) {
            $dir = public_path(self::EVIDENCE_FOLDER);
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            $file     = $request->file('evidence_image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $fileName);
            $imagePath = self::EVIDENCE_FOLDER . '/' . $fileName;
        } elseif ($request->filled('evidence_photo')) {
            $imagePath = $this->photos->storeBase64($validated['evidence_photo'], 'evidence_photo', self::EVIDENCE_FOLDER);
        }

        try {
            $overtime = OvertimeRequest::create([
                'company_id'     => $user->company_id,
                'user_id'        => $user->id,
                'attendance_id'  => $attendance?->id,
                'date'           => $date,
                'start_time'     => $validated['start_time'] . ':00',
                'end_time'       => $validated['end_time'] . ':00',
                'minutes'        => $minutes,
                'reason'         => $validated['reason'],
                'evidence_image' => $imagePath,
                'status'         => 'pending',
            ]);
        } catch (\Throwable $e) {
            $this->photos->delete($imagePath);
            throw $e;
        }

        $overtime->load(['attendance', 'approver:id,name']);

        return response()->json([
            'status'  => true,
            'message' => 'Pengajuan lembur berhasil dibuat',
            'data'    => array_merge($overtime->toApiArray(), [
                'attendance' => $this->attendanceBrief($overtime->attendance),
            ]),
        ], 201);
    }

    // ============================================================
    // POST /api/company/employee/overtimes/{id}/cancel
    // ============================================================
    public function cancel(Request $request, int $id)
    {
        $this->ensureEmployee();
        $user = $request->user();

        $overtime = OvertimeRequest::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if ($overtime->status !== 'pending') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya pengajuan lembur status pending yang bisa dibatalkan.',
            ], 422);
        }

        $overtime->update(['status' => 'canceled']);

        return response()->json([
            'status'  => true,
            'message' => 'Pengajuan lembur dibatalkan',
            'data'    => $overtime->fresh('approver:id,name')->toApiArray(),
        ]);
    }
}
