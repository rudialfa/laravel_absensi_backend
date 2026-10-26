<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrCompanyOvertimeRequestController extends Controller
{
    private const USER_COLUMNS = 'user:id,name,email,department,position,image_url';

    // Sebelumnya TIDAK ADA cek role: karyawan biasa bisa approve lembur sendiri
    // lewat endpoint HR kalau route tidak dijaga middleware.
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function format(OvertimeRequest $o, bool $withAttendance = false): array
    {
        $data = array_merge($o->toApiArray(), [
            'user' => $o->user ? [
                'id'         => $o->user->id,
                'name'       => $o->user->name,
                'email'      => $o->user->email,
                'department' => $o->user->department,
                'position'   => $o->user->position,
                'image_url'  => $o->user->image_url ? asset($o->user->image_url) : null,
            ] : null,
        ]);

        if ($withAttendance) {
            $a = $o->attendance;
            $data['attendance'] = $a ? [
                'id'            => $a->id,
                'date'          => $a->date?->toDateString(),
                'time_in'       => $a->time_in,
                'time_out'      => $a->time_out,
                'scheduled_in'  => $a->scheduled_in,
                'scheduled_out' => $a->scheduled_out,
                'status'        => $a->status,
                'photo_in_url'  => $a->photo_in_url,
                'photo_out_url' => $a->photo_out_url,
            ] : null;
        }

        return $data;
    }

    private function findAttendance(OvertimeRequest $o): ?Attendance
    {
        if ($o->attendance_id) {
            $a = Attendance::where('company_id', $o->company_id)->find($o->attendance_id);
            if ($a) return $a;
        }

        return Attendance::where('company_id', $o->company_id)
            ->where('user_id', $o->user_id)
            ->whereDate('date', $o->date)
            ->first();
    }

    // ============================================================
    // GET /api/company/hr/overtimes
    // filter: status, user_id, from, to, q, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();
        $hr = $request->user();

        $q = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->orderByRaw("status = 'pending' DESC")
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('user_id')) $q->where('user_id', (int) $request->user_id);
        if ($request->filled('from'))    $q->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('date', '<=', $request->to);
        if ($request->filled('q')) {
            $kw = $request->q;
            $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$kw}%"));
        }

        $page = $q->paginate((int) $request->get('per_page', 10));
        $page->getCollection()->transform(fn($o) => $this->format($o));

        return response()->json([
            'status'  => true,
            'message' => 'List overtime requests (HR)',
            'counts'  => OvertimeRequest::where('company_id', $hr->company_id)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'data'    => $page,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/overtimes/{id}
    // ============================================================
    public function show(Request $request, int $id)
    {
        $this->ensureHr();
        $hr = $request->user();

        $o = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->with([self::USER_COLUMNS, 'attendance', 'approver:id,name'])
            ->findOrFail($id);

        // Kalau attendance_id kosong, tampilkan absen di tanggal yang sama
        if (!$o->attendance) {
            $o->setRelation('attendance', $this->findAttendance($o));
        }

        return response()->json([
            'status'  => true,
            'message' => 'Overtime request detail (HR)',
            'data'    => array_merge($this->format($o, true), [
                'can_approve' => $o->status === 'pending',
            ]),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/overtimes/{id}/approve
    // body (opsional): { approval_note }
    // ============================================================
    public function approve(Request $request, int $id)
    {
        $this->ensureHr();
        $hr = $request->user();
        $request->validate(['approval_note' => ['nullable', 'string', 'max:500']]);

        return DB::transaction(function () use ($hr, $request, $id) {
            $overtime = OvertimeRequest::query()
                ->lockForUpdate()
                ->where('company_id', $hr->company_id)
                ->findOrFail($id);

            if ($overtime->status !== 'pending') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Hanya request pending yang bisa di-approve.',
                ], 422);
            }

            $attendance = $this->findAttendance($overtime);

            $overtime->update([
                'status'        => 'approved',
                'approved_by'   => $hr->id,
                'approved_at'   => now(),
                'approval_note' => $request->approval_note,
                'attendance_id' => $overtime->attendance_id ?? $attendance?->id,
            ]);

            // Sebelumnya: status absen ditimpa jadi 'overtime' dan marked_by = HR.
            // Akibatnya status 'late' hilang dari rekap & absen dianggap input manual.
            // Sekarang hanya kolom lembur yang diubah.
            if ($attendance) {
                $attendance->update([
                    'overtime_minutes'  => (int) $overtime->minutes,
                    'approved_overtime' => true,
                ]);
            }

            $overtime->load([self::USER_COLUMNS, 'attendance', 'approver:id,name']);

            return response()->json([
                'status'  => true,
                'message' => 'Lembur disetujui',
                'data'    => array_merge($this->format($overtime, true), [
                    'attendance_updated' => (bool) $attendance,
                ]),
            ]);
        });
    }

    // ============================================================
    // POST /api/company/hr/overtimes/{id}/reject
    // body: { approval_note } — alasan penolakan wajib
    // ============================================================
    public function reject(Request $request, int $id)
    {
        $this->ensureHr();
        $hr = $request->user();
        $request->validate(
            ['approval_note' => ['required', 'string', 'min:3', 'max:500']],
            ['approval_note.required' => 'Alasan penolakan wajib diisi.']
        );

        $overtime = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->findOrFail($id);

        if ($overtime->status !== 'pending') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya request pending yang bisa di-reject.',
            ], 422);
        }

        $overtime->update([
            'status'        => 'rejected',
            'approved_by'   => $hr->id,
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Lembur ditolak',
            'data'    => $this->format($overtime->fresh([self::USER_COLUMNS, 'approver:id,name'])),
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/overtimes/export?month=&year=&status=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();
        $hr = $request->user();

        $validated = $request->validate([
            'month'  => 'required|integer|between:1,12',
            'year'   => 'required|integer|min:2020|max:2099',
            'status' => 'nullable|in:pending,approved,rejected,canceled',
        ]);

        $month  = (int) $validated['month'];
        $year   = (int) $validated['year'];
        $status = $validated['status'] ?? null;

        $q = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->orderBy('date')
            ->orderBy('user_id');

        if ($status) $q->where('status', $status);

        $overtimes = $q->get();

        $periodLabel = (OvertimeRequest::MONTH_NAMES[$month] ?? $month) . ' ' . $year;
        $statusLabel = $status ? ' - ' . ucfirst($status) : '';
        $fileName    = 'overtime-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_overtime', [
            'company'       => $hr->company ?? (object) ['name' => ''],
            'periodLabel'   => $periodLabel . $statusLabel,
            'overtimes'     => $overtimes,
            'totalPending'  => $overtimes->where('status', 'pending')->count(),
            'totalApproved' => $overtimes->where('status', 'approved')->count(),
            'totalRejected' => $overtimes->where('status', 'rejected')->count(),
            'totalMinutes'  => $overtimes->where('status', 'approved')->sum('minutes'),
            'generatedAt'   => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
