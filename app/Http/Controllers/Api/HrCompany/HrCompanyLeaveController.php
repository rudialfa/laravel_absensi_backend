<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Leaves;
use App\Services\Notifiers\ApprovalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrCompanyLeaveController extends Controller
{
    private const USER_COLUMNS = 'user:id,name,email,department,position,image_url';

    public function __construct(private ApprovalNotifier $approvals) {}

    // Sebelumnya tidak ada cek role
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    // ============================================================
    // GET /api/company/hr/leaves
    // filter: status, user_id, type, from, to, q, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

        $q = Leaves::query()
            ->where('company_id', $this->companyId())
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->orderByRaw("status = 'pending' DESC")
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('type'))    $q->where('type', $request->type);
        if ($request->filled('user_id')) $q->where('user_id', (int) $request->user_id);
        if ($request->filled('from'))    $q->whereDate('end_date', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('start_date', '<=', $request->to);
        if ($request->filled('q')) {
            $kw = $request->q;
            $q->whereHas('user', fn($u) => $u->where('name', 'like', "%{$kw}%"));
        }

        $page = $q->paginate((int) $request->get('per_page', 10));
        $page->getCollection()->transform(fn($l) => $l->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'List leaves (HR)',
            'counts'  => Leaves::where('company_id', $this->companyId())
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'data'    => $page,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/leaves/{id}
    // ============================================================
    public function show(Request $request, int $id)
    {
        $this->ensureHr();

        $leave = Leaves::query()
            ->where('company_id', $this->companyId())
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->findOrFail($id);

        // Riwayat cuti karyawan ini di tahun yang sama
        $yearStats = Leaves::where('company_id', $this->companyId())
            ->where('user_id', $leave->user_id)
            ->where('status', 'approved')
            ->whereYear('start_date', $leave->start_date->year)
            ->get(['start_date', 'end_date', 'type']);

        return response()->json([
            'status'  => true,
            'message' => 'Leave detail (HR)',
            'data'    => array_merge($leave->toApiArray(), [
                'can_approve'     => $leave->status === 'pending',
                'year_summary'    => [
                    'year'          => $leave->start_date->year,
                    'approved'      => $yearStats->count(),
                    'approved_days' => $yearStats->sum(fn($l) => $l->total_days),
                ],
            ]),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/leaves/{id}/approve
    // body (opsional): { approval_note }
    // → notifikasi ke karyawan
    // ============================================================
    public function approve(Request $request, int $id)
    {
        $this->ensureHr();
        $request->validate(['approval_note' => ['nullable', 'string', 'max:500']]);

        $hr = $request->user();

        $leave = DB::transaction(function () use ($id, $hr, $request) {
            $leave = Leaves::query()
                ->lockForUpdate()
                ->where('company_id', $this->companyId())
                ->findOrFail($id);

            if ($leave->status !== 'pending') {
                abort(response()->json([
                    'status'  => false,
                    'message' => 'Hanya cuti pending yang bisa di-approve.',
                ], 422));
            }

            $leave->update([
                'status'        => 'approved',
                'approved_by'   => $hr->id,
                'approved_at'   => now(),
                'approval_note' => $request->approval_note,
            ]);

            return $leave;
        });

        // 🔔 Notifikasi ke karyawan
        $this->approvals->decided(ApprovalNotifier::KIND_LEAVE, $leave, true, $request->approval_note);

        return response()->json([
            'status'  => true,
            'message' => 'Cuti disetujui',
            'data'    => $leave->fresh([self::USER_COLUMNS, 'approver:id,name'])->toApiArray(),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/leaves/{id}/reject
    // body: { approval_note } — alasan penolakan WAJIB
    // → notifikasi ke karyawan
    // ============================================================
    public function reject(Request $request, int $id)
    {
        $this->ensureHr();
        $request->validate(
            ['approval_note' => ['required', 'string', 'min:3', 'max:500']],
            ['approval_note.required' => 'Alasan penolakan wajib diisi.']
        );

        $leave = Leaves::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya cuti pending yang bisa di-reject.',
            ], 422);
        }

        $leave->update([
            'status'        => 'rejected',
            'approved_by'   => $request->user()->id,
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        // 🔔 Notifikasi ke karyawan
        $this->approvals->decided(ApprovalNotifier::KIND_LEAVE, $leave, false, $request->approval_note);

        return response()->json([
            'status'  => true,
            'message' => 'Cuti ditolak',
            'data'    => $leave->fresh([self::USER_COLUMNS, 'approver:id,name'])->toApiArray(),
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/leaves/export?status=&user_id=&from=&to=&month=&year=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $user = $request->user();

        $q = Leaves::query()
            ->where('company_id', $this->companyId())
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('user_id')) $q->where('user_id', (int) $request->user_id);
        if ($request->filled('from'))    $q->whereDate('end_date', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('start_date', '<=', $request->to);

        if ($request->filled('month') && $request->filled('year')) {
            $q->whereMonth('start_date', $request->month)
                ->whereYear('start_date', $request->year);
        } elseif ($request->filled('year')) {
            $q->whereYear('start_date', $request->year);
        }

        $leaves = $q->get();

        $stats = [
            'total'    => $leaves->count(),
            'pending'  => $leaves->where('status', 'pending')->count(),
            'approved' => $leaves->where('status', 'approved')->count(),
            'rejected' => $leaves->where('status', 'rejected')->count(),
        ];

        $bulanLabel = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $month       = $request->month ? (int) $request->month : null;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? ($bulanLabel[$month] ?? $month) . ' ' . $year
            : ($request->filled('from') && $request->filled('to')
                ? $request->from . ' s/d ' . $request->to
                : ($request->filled('year') ? 'Tahun ' . $year : 'Semua Periode'));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_leave', [
            'company'     => $user->company ?? (object) ['name' => ''],
            'leaves'      => $leaves,
            'stats'       => $stats,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('leaves-' . now()->format('Y-m-d') . '.pdf');
    }
}
