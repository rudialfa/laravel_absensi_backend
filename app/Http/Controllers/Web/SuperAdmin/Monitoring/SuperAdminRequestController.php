<?php

namespace App\Http\Controllers\Web\SuperAdmin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Leaves;
use App\Models\OvertimeRequest;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring pengajuan karyawan: Izin, Cuti, Lembur (3 tab dalam 1 menu).
 * Persetujuan tetap wewenang HR — superadmin memantau (termasuk pengajuan yang lama
 * tidak diproses) dan menghapus data yang salah.
 */
class SuperAdminRequestController extends Controller
{
    public const TABS = [
        'permissions' => 'Izin',
        'leaves'      => 'Cuti',
        'overtimes'   => 'Lembur',
    ];

    public const STATUS_LABELS = [
        'pending'  => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'canceled' => 'Dibatalkan',
    ];

    public const STATUS_BADGE = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'canceled' => 'secondary',
    ];

    public const LEAVE_TYPES = [
        'annual'    => 'Cuti tahunan',
        'sick'      => 'Sakit',
        'maternity' => 'Melahirkan',
        'important' => 'Alasan penting',
        'other'     => 'Lainnya',
    ];

    /** Pengajuan pending lebih lama dari ini dianggap "terlalu lama belum diproses" */
    public const STALE_DAYS = 3;

    // =========================================================
    // INDEX (?tab=permissions|leaves|overtimes)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'permissions';

        $items = $this->filtered($tab, $request)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderByRaw($this->pendingFirstSql($tab))
            ->orderByDesc($this->dateColumn($tab))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Jumlah per status untuk tab aktif (sesuai filter selain status)
        $statusCounts = collect(self::STATUS_LABELS)->keys()->mapWithKeys(function ($status) use ($tab, $request) {
            $q = $this->filtered($tab, $request, ignoreStatus: true);
            $this->applyStatus($tab, $q, $status);

            return [$status => $q->count()];
        });

        // Pending per tab (badge di tab) & yang sudah lama menunggu
        $pendingPerTab = [];
        $stalePerTab   = [];
        foreach (array_keys(self::TABS) as $t) {
            $q = $this->baseQuery($t);
            $this->applyStatus($t, $q, 'pending');
            $pendingPerTab[$t] = (clone $q)->count();
            $stalePerTab[$t]   = (clone $q)->where('created_at', '<', now()->subDays(self::STALE_DAYS))->count();
        }

        return view('pages.superadmin.monitoring.requests.index', [
            'tab'           => $tab,
            'items'         => $items,
            'statusCounts'  => $statusCounts,
            'pendingPerTab' => $pendingPerTab,
            'stalePerTab'   => $stalePerTab,
            'companies'     => Company::where('type', 'company')->orderBy('name')->get(['id', 'name']),
            // Nama tenant via map (model Leaves belum tentu punya relasi company())
            'companyNames'  => Company::pluck('name', 'id'),
            'tabs'          => self::TABS,
            'statusLabels'  => self::STATUS_LABELS,
            'badge'         => self::STATUS_BADGE,
            'typeOptions'   => $this->typeOptions($tab),
            'staleDays'     => self::STALE_DAYS,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show(string $tab, $id)
    {
        abort_unless(array_key_exists($tab, self::TABS), 404);

        $relations = ['user:id,name,email,phone,department,position', 'approver:id,name'];
        if ($tab === 'overtimes') {
            $relations[] = 'attendance';
        }

        $item = $this->baseQuery($tab)->with($relations)->findOrFail($id);

        // Riwayat pengajuan jenis yang sama dari karyawan ini (tahun berjalan)
        $history = $this->baseQuery($tab)
            ->where('user_id', $item->user_id)
            ->where('id', '!=', $item->id)
            ->whereYear($this->dateColumn($tab), now()->year)
            ->orderByDesc($this->dateColumn($tab))
            ->limit(10)
            ->get();

        // Absen yang terhubung dengan izin ini
        $linkedAttendances = $tab === 'permissions'
            ? \App\Models\Attendance::where('permission_id', $item->id)->orderBy('date')->get(['id', 'date', 'time_in', 'time_out', 'status'])
            : collect();

        return view('pages.superadmin.monitoring.requests.show', [
            'tab'               => $tab,
            'item'              => $item,
            'company'           => Company::find($item->company_id),
            'status'            => $this->statusOf($tab, $item),
            'file'              => $this->fileOf($tab, $item),
            'history'           => $history,
            'linkedAttendances' => $linkedAttendances,
            'tabs'              => self::TABS,
            'statusLabels'      => self::STATUS_LABELS,
            'badge'             => self::STATUS_BADGE,
            'leaveTypes'        => self::LEAVE_TYPES,
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        abort_unless(array_key_exists($tab, self::TABS), 404);

        $item = $this->baseQuery($tab)->with('user:id,name')->findOrFail($id);
        $companyName = Company::whereKey($item->company_id)->value('name');

        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $snapshot = $item->getAttributes();
        $file     = $this->fileOf($tab, $item);
        $label    = self::TABS[$tab];

        $item->delete();

        // Hapus lampiran kalau file lokal
        if ($file && !str_starts_with($file, 'http') && File::exists(public_path($file))) {
            File::delete(public_path($file));
        }

        AuditLog::record(
            'delete_' . rtrim($tab, 's'),
            null,
            "{$label} {$item->user?->name} ({$companyName}) dihapus. Alasan: {$data['reason']}",
            array_merge($snapshot, ['reason' => $data['reason']])
        );

        return redirect()
            ->route('superadmin.monitoring.requests.index', ['tab' => $tab, 'company_id' => $item->company_id])
            ->with('success', "Data {$label} dihapus.");
    }

    // =========================================================
    // EXPORT CSV (tab aktif + filter)
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'permissions';

        $query = $this->filtered($tab, $request)
            ->with(['user:id,name,department', 'approver:id,name'])
            ->orderBy('company_id')
            ->orderBy($this->dateColumn($tab));

        AuditLog::record('export_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        $companyNames = Company::pluck('name', 'id');

        return response()->streamDownload(function () use ($query, $tab, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tenant', 'Karyawan', 'Departemen', 'Jenis', 'Mulai', 'Selesai', 'Jam', 'Alasan', 'Status', 'Diproses oleh', 'Diproses pada', 'Catatan HR', 'Diajukan'], ';');

            $query->chunk(500, function ($rows) use ($out, $tab, $companyNames) {
                foreach ($rows as $item) {
                    [$start, $end, $time] = $this->periodOf($tab, $item);
                    fputcsv($out, [
                        $companyNames[$item->company_id] ?? '-',
                        $item->user->name ?? '-',
                        $item->user->department ?? '',
                        $this->typeLabel($tab, $item),
                        $start,
                        $end,
                        $time,
                        $item->reason,
                        self::STATUS_LABELS[$this->statusOf($tab, $item)] ?? '',
                        $item->approver->name ?? '',
                        $item->approved_at ? Carbon::parse($item->approved_at)->format('Y-m-d H:i') : '',
                        $item->approval_note,
                        optional($item->created_at)->format('Y-m-d H:i'),
                    ], ';');
                }
            });

            fclose($out);
        }, strtolower(self::TABS[$tab]) . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // HELPER (dipakai juga oleh view lewat static)
    // =========================================================

    private function baseQuery(string $tab): Builder
    {
        return match ($tab) {
            'leaves'    => Leaves::query(),
            'overtimes' => OvertimeRequest::query(),
            default     => Permission::query(),
        };
    }

    private function dateColumn(string $tab): string
    {
        return match ($tab) {
            'leaves'    => 'start_date',
            'overtimes' => 'date',
            default     => 'date_permission',
        };
    }

    private function pendingFirstSql(string $tab): string
    {
        return $tab === 'permissions' ? 'is_approved IS NULL DESC' : "status = 'pending' DESC";
    }

    /** Izin memakai kolom is_approved (null/1/0), cuti & lembur memakai kolom status */
    private function applyStatus(string $tab, Builder $q, string $status): void
    {
        if ($tab === 'permissions') {
            match ($status) {
                'pending'  => $q->whereNull('is_approved'),
                'approved' => $q->where('is_approved', true),
                'rejected' => $q->where('is_approved', false),
                default    => $q->whereRaw('1 = 0'), // izin tidak punya status dibatalkan
            };

            return;
        }

        $q->where('status', $status);
    }

    private function filtered(string $tab, Request $request, bool $ignoreStatus = false): Builder
    {
        $q = $this->baseQuery($tab);

        if ($request->filled('company_id')) {
            $q->where('company_id', $request->company_id);
        }

        if (!$ignoreStatus && $request->filled('status')) {
            $this->applyStatus($tab, $q, $request->status);
        }

        if ($request->boolean('stale')) {
            $this->applyStatus($tab, $q, 'pending');
            $q->where('created_at', '<', now()->subDays(self::STALE_DAYS));
        }

        if ($request->filled('type') && $tab !== 'overtimes') {
            $q->where('type', $request->type);
        }

        // Rentang tanggal: ambil pengajuan yang periodenya beririsan dengan filter
        if ($request->filled('from')) {
            $from = Carbon::parse($request->from)->toDateString();
            match ($tab) {
                'leaves'    => $q->whereDate('end_date', '>=', $from),
                'overtimes' => $q->whereDate('date', '>=', $from),
                default     => $q->whereRaw('COALESCE(end_date, date_permission) >= ?', [$from]),
            };
        }

        if ($request->filled('to')) {
            $q->whereDate($this->dateColumn($tab), '<=', Carbon::parse($request->to)->toDateString());
        }

        if ($request->filled('search')) {
            $like = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $q->where(function ($w) use ($like) {
                $w->whereHas('user', fn($u) => $u->where('name', 'like', $like))
                    ->orWhere('reason', 'like', $like);
            });
        }

        return $q;
    }

    private function typeOptions(string $tab): array
    {
        return match ($tab) {
            'leaves'      => self::LEAVE_TYPES,
            'permissions' => Permission::TYPE_LABELS,
            default       => [],
        };
    }

    public static function statusOf(string $tab, Model $item): string
    {
        if ($tab === 'permissions') {
            return match ($item->is_approved) {
                true    => 'approved',
                false   => 'rejected',
                default => 'pending',
            };
        }

        return (string) $item->status;
    }

    public static function typeLabel(string $tab, Model $item): string
    {
        return match ($tab) {
            'leaves'      => self::LEAVE_TYPES[$item->type] ?? ucfirst((string) $item->type),
            'overtimes'   => 'Lembur',
            default       => Permission::TYPE_LABELS[$item->type ?? Permission::TYPE_TIDAK_MASUK] ?? 'Izin',
        };
    }

    /** @return array{0:string,1:string,2:string} [mulai, selesai, jam] */
    public static function periodOf(string $tab, Model $item): array
    {
        $fmt  = fn($d) => $d ? Carbon::parse($d)->format('Y-m-d') : '';
        $time = fn($a, $b) => ($a || $b) ? trim(($a ? substr($a, 0, 5) : '') . ' – ' . ($b ? substr($b, 0, 5) : '')) : '';

        return match ($tab) {
            'leaves'    => [$fmt($item->start_date), $fmt($item->end_date), ''],
            'overtimes' => [$fmt($item->date), $fmt($item->date), $time($item->start_time, $item->end_time)],
            default     => [$fmt($item->date_permission), $fmt($item->end_date ?? $item->date_permission), $time($item->start_time, $item->end_time)],
        };
    }

    public static function fileOf(string $tab, Model $item): ?string
    {
        return match ($tab) {
            'leaves'    => $item->attachment,
            'overtimes' => $item->evidence_image,
            default     => $item->image,
        };
    }
}
