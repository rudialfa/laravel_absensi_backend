<?php

namespace App\Http\Controllers\Web\SuperAdmin\Boarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminSchoolClassController;
use App\Models\AuditLog;
use App\Models\BoardingPermission;
use App\Models\Company;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Izin Asrama (boarding_permissions): izin pulang / keluar santri yang diajukan wali
 * dan diproses pengasuh/admin pondok.
 *
 * Superadmin: pantau siapa yang sedang di luar pondok, siapa yang TERLAMBAT kembali,
 * pengajuan yang belum diproses, export, dan hapus pengajuan yang salah/ganda
 * (wajib alasan, tercatat di Audit Log).
 */
class SuperAdminBoardingPermissionController extends Controller
{
    public const VIEWS = [
        'all'     => 'Semua',
        'pending' => 'Menunggu',
        'outside' => 'Sedang di Luar',
        'late'    => 'Terlambat Kembali',
    ];

    public const STATUS = [
        'pending'       => 'Menunggu',
        'approved'      => 'Disetujui',
        'rejected'      => 'Ditolak',
        'sudah_kembali' => 'Sudah Kembali',
    ];
    public const STATUS_BADGE = [
        'pending'       => 'warning',
        'approved'      => 'primary',
        'rejected'      => 'danger',
        'sudah_kembali' => 'success',
    ];

    public const JENIS = ['izin_pulang' => 'Izin Pulang', 'izin_keluar' => 'Izin Keluar'];

    // =========================================================
    // INDEX (?view=all|pending|outside|late)
    // =========================================================
    public function index(Request $request)
    {
        $view = array_key_exists($request->view, self::VIEWS) ? $request->view : 'all';

        $items = $this->query($request, $view)
            ->with([
                'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'gender', 'deleted_at')->with(['classRoom:id,name', 'activeRoomAssignment.room.dormitory:id,name']),
                'submittedBy:id,name,phone',
                'reviewedBy:id,name',
            ])
            ->orderByRaw("status = 'pending' DESC")
            ->orderByDesc('tanggal_keluar')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $base = new Request($request->only(['company_id']));

        return view('pages.superadmin.boarding.permissions.index', [
            'items'        => $items,
            'view'         => $view,
            'views'        => self::VIEWS,
            'counts'       => collect(array_keys(self::VIEWS))->mapWithKeys(fn($v) => [$v => $this->query($base, $v)->count()]),
            'schools'      => Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name']),
            'companyNames' => Company::pluck('name', 'id'),
            'statusLabels' => self::STATUS,
            'statusBadge'  => self::STATUS_BADGE,
            'jenis'        => self::JENIS,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $item = BoardingPermission::with([
            'company:id,name',
            'student' => fn($q) => $q->withTrashed()->with(['classRoom:id,name', 'activeRoomAssignment.room.dormitory:id,name', 'guardians:id,name,phone']),
            'submittedBy:id,name,phone,email,role',
            'reviewedBy:id,name,role',
        ])->findOrFail($id);

        return view('pages.superadmin.boarding.permissions.show', [
            'item'         => $item,
            'state'        => self::state($item),
            'history'      => BoardingPermission::where('student_id', $item->student_id)->where('id', '!=', $item->id)
                ->latest('tanggal_keluar')->limit(10)->get(),
            'statusLabels' => self::STATUS,
            'statusBadge'  => self::STATUS_BADGE,
            'jenis'        => self::JENIS,
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $item = BoardingPermission::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
        $snapshot = $item->getAttributes();
        $item->delete();

        AuditLog::record(
            'delete_boarding_permission',
            null,
            (self::JENIS[$item->jenis] ?? $item->jenis) . " santri {$item->student?->name} tanggal " . optional($item->tanggal_keluar)->format('Y-m-d') . " (tenant #{$item->company_id}) dihapus. Alasan: {$data['reason']}",
            $snapshot + ['reason' => $data['reason']]
        );

        return redirect()->route('superadmin.boarding.permissions.index', ['company_id' => $item->company_id])->with('success', 'Pengajuan izin asrama dihapus.');
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $view = array_key_exists($request->view, self::VIEWS) ? $request->view : 'all';
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_boarding_permissions', null, 'Export izin asrama (' . self::VIEWS[$view] . ') ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($request, $view, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sekolah', 'Santri', 'NIS', 'Kelas', 'Jenis', 'Alasan', 'Keluar', 'Rencana kembali', 'Kembali aktual', 'Terlambat (hari)', 'Penjemput', 'Hubungan', 'Kontak', 'Diajukan oleh', 'Status', 'Diproses oleh', 'Catatan review'], ';');

            $this->query($request, $view)
                ->with(['student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'), 'submittedBy:id,name', 'reviewedBy:id,name'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($out, $companyNames) {
                    foreach ($rows as $p) {
                        fputcsv($out, [
                            $companyNames[$p->company_id] ?? '-',
                            $p->student->name ?? '',
                            $p->student->nis ?? '',
                            $p->student->classRoom->name ?? '',
                            self::JENIS[$p->jenis] ?? $p->jenis,
                            $p->alasan,
                            optional($p->tanggal_keluar)->format('Y-m-d'),
                            optional($p->tanggal_kembali_rencana)->format('Y-m-d'),
                            optional($p->tanggal_kembali_aktual)->format('Y-m-d'),
                            self::lateDays($p) ?: '',
                            $p->nama_penjemput,
                            $p->hubungan_penjemput,
                            $p->kontak_penjemput,
                            $p->submittedBy->name ?? '',
                            self::STATUS[$p->status] ?? $p->status,
                            $p->reviewedBy->name ?? '',
                            $p->catatan_review,
                        ], ';');
                    }
                });

            fclose($out);
        }, 'izin-asrama-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY & HELPER
    // =========================================================
    private function query(Request $request, string $view)
    {
        $today = today()->toDateString();

        return BoardingPermission::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($view === 'pending', fn($q) => $q->where('status', 'pending'))
            // Disetujui, sudah lewat tanggal keluar, belum ditandai kembali
            ->when($view === 'outside', fn($q) => $q->where('status', 'approved')->whereDate('tanggal_keluar', '<=', $today))
            // Masih di luar padahal tanggal rencana kembali sudah lewat
            ->when($view === 'late', fn($q) => $q->where('status', 'approved')->whereDate('tanggal_kembali_rencana', '<', $today))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->jenis, fn($q, $j) => $q->where('jenis', $j))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('tanggal_keluar', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('tanggal_keluar', '<=', $d))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('nama_penjemput', 'like', $like)
                    ->orWhereIn('student_id', Student::withTrashed()->where(fn($st) => $st->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id')));
            });
    }

    /** Keadaan izin saat ini untuk badge: [label, badge] */
    public static function state(BoardingPermission $p): array
    {
        $today = today();

        if ($p->status === 'approved') {
            if ($p->tanggal_kembali_rencana && $p->tanggal_kembali_rencana->lt($today)) {
                return ['Terlambat ' . $p->tanggal_kembali_rencana->diffInDays($today) . ' hari', 'danger'];
            }
            if ($p->tanggal_keluar && $p->tanggal_keluar->lte($today)) {
                return ['Sedang di luar', 'info'];
            }

            return ['Belum berangkat', 'light'];
        }

        if ($p->status === 'sudah_kembali' && ($late = self::lateDays($p)) > 0) {
            return ['Kembali terlambat ' . $late . ' hari', 'warning'];
        }

        return [self::STATUS[$p->status] ?? $p->status, self::STATUS_BADGE[$p->status] ?? 'light'];
    }

    /** Berapa hari terlambat kembali (0 kalau tepat waktu / belum lewat) */
    public static function lateDays(BoardingPermission $p): int
    {
        if (!$p->tanggal_kembali_rencana) {
            return 0;
        }

        $back = $p->tanggal_kembali_aktual ?? ($p->status === 'approved' ? today() : null);
        if (!$back) {
            return 0;
        }

        return max(0, (int) Carbon::parse($p->tanggal_kembali_rencana)->diffInDays($back, false));
    }
}
