<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Permission;
use App\Services\AttendanceRuleService;
use App\Services\Notifiers\ApprovalNotifier;
use Illuminate\Http\Request;

class HrCompanyPermissionController extends Controller
{
    public function __construct(
        private AttendanceRuleService $rules,
        private ApprovalNotifier $approvals,
    ) {}

    // Kolom user yang aman dikirim. JANGAN kirim seluruh user:
    // face_embedding bisa puluhan KB dan ikut terkirim di setiap baris.
    private const USER_COLUMNS = 'user:id,name,email,phone,position,department,image_url';

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json(['status' => false, 'message' => 'Company ID tidak ditemukan'], 422));
        }
        return (int) $companyId;
    }

    private function findOrFail($id): Permission
    {
        return Permission::query()
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    private function applyFilters($q, Request $request)
    {
        if ($request->filled('status')) {
            match ($request->status) {
                'pending'  => $q->whereNull('is_approved'),
                'approved' => $q->where('is_approved', true),
                'rejected' => $q->where('is_approved', false),
                default    => null,
            };
        }

        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }

        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->whereHas('user', function ($u) use ($keyword) {
                $u->where(function ($w) use ($keyword) {
                    $w->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            });
        }

        // Filter berdasarkan TANGGAL IZIN, bukan tanggal pengajuan
        if ($request->filled('date_from')) {
            $q->whereDate('date_permission', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('date_permission', '<=', $request->date_to);
        }
        if ($request->filled('month') && $request->filled('year')) {
            $q->whereMonth('date_permission', $request->month)
                ->whereYear('date_permission', $request->year);
        } elseif ($request->filled('year')) {
            $q->whereYear('date_permission', $request->year);
        }

        return $q;
    }

    private function format(Permission $p): array
    {
        return [
            'id'              => $p->id,
            'type'            => $p->type,
            'type_label'      => $p->type_label,
            'date_permission' => $p->date_permission?->toDateString(),
            'end_date'        => $p->end_date?->toDateString(),
            'total_days'      => $p->date_permission
                ? $p->date_permission->diffInDays($p->end_date ?? $p->date_permission, true) + 1
                : 1,
            'start_time'      => $p->start_time ? substr($p->start_time, 0, 5) : null,
            'end_time'        => $p->end_time ? substr($p->end_time, 0, 5) : null,
            'reason'          => $p->reason,
            'image'           => $p->image,
            'image_url'       => $p->image ? asset($p->image) : null,
            'is_approved'     => $p->is_approved,
            'status'          => $p->status_label, // pending | approved | rejected
            'approved_by'     => $p->approver ? ['id' => $p->approver->id, 'name' => $p->approver->name] : null,
            'approved_at'     => $p->approved_at?->toDateTimeString(),
            'approval_note'   => $p->approval_note,
            'created_at'      => $p->created_at?->toDateTimeString(),
            'user'            => $p->user ? [
                'id'         => $p->user->id,
                'name'       => $p->user->name,
                'email'      => $p->user->email,
                'phone'      => $p->user->phone,
                'position'   => $p->user->position,
                'department' => $p->user->department,
                'image_url'  => $p->user->image_url ? asset($p->user->image_url) : null,
            ] : null,
        ];
    }

    // ============================================================
    // GET /api/company/hr/permissions
    // filter: status, type, user_id, q, date_from, date_to, month, year
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

        $q = Permission::query()
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->where('company_id', $this->companyId());

        $page = $this->applyFilters($q, $request)
            ->orderByRaw('is_approved IS NULL DESC') // pending tampil paling atas
            ->orderByDesc('date_permission')
            ->orderByDesc('id')
            ->paginate((int) $request->query('per_page', 20));

        $page->getCollection()->transform(fn($p) => $this->format($p));

        $base = Permission::where('company_id', $this->companyId());

        return response()->json([
            'status'  => true,
            'message' => 'List permission company',
            'counts'  => [
                'pending'  => (clone $base)->whereNull('is_approved')->count(),
                'approved' => (clone $base)->where('is_approved', true)->count(),
                'rejected' => (clone $base)->where('is_approved', false)->count(),
            ],
            'data' => $page,
        ]);
    }

    // ============================================================
    // GET /api/company/hr/permissions/{id}
    //
    // Sebelumnya detail memakai filter list (status/q). Kalau aplikasi
    // mengirim ?status=pending lalu membuka izin yang sudah approved,
    // hasilnya 404. Sekarang detail hanya dicari berdasarkan id + company.
    // ============================================================
    public function show($id)
    {
        $this->ensureHr();

        $perm = $this->findOrFail($id);

        // Absen karyawan di tanggal izin (untuk dicocokkan HR)
        $start = $perm->date_permission->toDateString();
        $end   = ($perm->end_date ?? $perm->date_permission)->toDateString();

        $attendances = Attendance::where('company_id', $this->companyId())
            ->where('user_id', $perm->user_id)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->map(fn($a) => [
                'id'                   => $a->id,
                'date'                 => $a->date?->toDateString(),
                'time_in'              => $a->time_in,
                'time_out'             => $a->time_out,
                'scheduled_in'         => $a->scheduled_in,
                'scheduled_out'        => $a->scheduled_out,
                'status'               => $a->status,
                'late_minutes'         => (int) $a->late_minutes,
                'early_leave_minutes'  => (int) $a->early_leave_minutes,
                'photo_in_url'         => $a->photo_in_url,
                'photo_out_url'        => $a->photo_out_url,
                'latlon_in'            => $a->latlon_in,
                'distance_in_m'        => $a->distance_in_m,
                'is_outside_radius_in' => (bool) $a->is_outside_radius_in,
                'note'                 => $a->note,
            ])->values();

        // Rekap izin karyawan ini di bulan yang sama
        $monthStart = $perm->date_permission->copy()->startOfMonth()->toDateString();
        $monthEnd   = $perm->date_permission->copy()->endOfMonth()->toDateString();

        $monthly = Permission::where('company_id', $this->companyId())
            ->where('user_id', $perm->user_id)
            ->whereBetween('date_permission', [$monthStart, $monthEnd])
            ->selectRaw('type, COUNT(*) as total, SUM(is_approved = 1) as approved')
            ->groupBy('type')
            ->get()
            ->map(fn($r) => [
                'type'       => $r->type,
                'type_label' => Permission::TYPE_LABELS[$r->type] ?? $r->type,
                'total'      => (int) $r->total,
                'approved'   => (int) $r->approved,
            ])->values();

        return response()->json([
            'status'  => true,
            'message' => 'Detail permission',
            'data'    => array_merge($this->format($perm), [
                'attendances'      => $attendances,
                'monthly_summary'  => $monthly,
                'can_approve'      => $perm->is_approved === null,
            ]),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/permissions/{id}/approve
    // body (opsional): { note }
    // ============================================================
    public function approve(Request $request, $id)
    {
        $this->ensureHr();
        $request->validate(['note' => 'nullable|string|max:500']);

        $perm = $this->findOrFail($id);

        if ($perm->is_approved !== null) {
            return response()->json([
                'status'  => false,
                'message' => 'Izin ini sudah ' . ($perm->is_approved ? 'disetujui' : 'ditolak') . ' sebelumnya.',
            ], 422);
        }

        $perm->update([
            'is_approved'   => true,
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'approval_note' => $request->input('note'),
        ]);

        // Koreksi absen yang sudah terlanjur tercatat telat / pulang cepat
        $this->rules->recalculateForPermission($perm);

        // 🔔 Notifikasi ke karyawan
        $this->approvals->decided(ApprovalNotifier::KIND_PERMISSION, $perm, true, $request->input('note'));

        return response()->json([
            'status'  => true,
            'message' => 'Izin disetujui',
            'data'    => $this->format($perm->fresh([self::USER_COLUMNS, 'approver:id,name'])),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/permissions/{id}/reject
    // body: { note } — alasan penolakan wajib
    // ============================================================
    public function reject(Request $request, $id)
    {
        $this->ensureHr();
        $request->validate(
            ['note' => 'required|string|min:3|max:500'],
            ['note.required' => 'Alasan penolakan wajib diisi.']
        );

        $perm = $this->findOrFail($id);

        if ($perm->is_approved !== null) {
            return response()->json([
                'status'  => false,
                'message' => 'Izin ini sudah ' . ($perm->is_approved ? 'disetujui' : 'ditolak') . ' sebelumnya.',
            ], 422);
        }

        $perm->update([
            'is_approved'   => false,
            'approved_by'   => auth()->id(),
            'approved_at'   => now(),
            'approval_note' => $request->input('note'),
        ]);

        $this->rules->recalculateForPermission($perm);

        // 🔔 Notifikasi ke karyawan
        $this->approvals->decided(ApprovalNotifier::KIND_PERMISSION, $perm, false, $request->input('note'));

        return response()->json([
            'status'  => true,
            'message' => 'Izin ditolak',
            'data'    => $this->format($perm->fresh([self::USER_COLUMNS, 'approver:id,name'])),
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/permissions/export
    // filter sama dengan index
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $q = Permission::query()
            ->with([self::USER_COLUMNS, 'approver:id,name'])
            ->where('company_id', $this->companyId());

        $permissions = $this->applyFilters($q, $request)
            ->orderByDesc('date_permission')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total'    => $permissions->count(),
            'pending'  => $permissions->whereNull('is_approved')->count(),
            'approved' => $permissions->where('is_approved', true)->count(),
            'rejected' => $permissions->where('is_approved', false)->count(),
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

        $month       = $request->month;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? ($bulanLabel[(int) $month] ?? $month) . ' ' . $year
            : ($request->filled('year') ? 'Tahun ' . $year : 'Semua Periode');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_permission', [
            'company'     => auth()->user()->company ?? (object) ['name' => ''],
            'permissions' => $permissions,
            'stats'       => $stats,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('permissions-' . now()->format('Y-m-d') . '.pdf');
    }
}
