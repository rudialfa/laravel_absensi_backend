<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit Log — hanya-baca (tidak ada edit/hapus, supaya jejak tidak bisa dihilangkan dari panel).
 *
 *  - Daftar + filter: kata kunci, jenis aksi, aksi, pelaku, tenant, jenis data, IP, rentang tanggal.
 *  - Ringkasan: hari ini, 7 hari, aksi hapus 7 hari, aksi terbanyak & pelaku teraktif 30 hari.
 *  - Detail: deskripsi, perbandingan before/after, meta lengkap, link ke data terkait, log lain untuk data yang sama.
 *  - Export CSV sesuai filter (export-nya sendiri ikut tercatat).
 */
class SuperAdminAuditLogController extends Controller
{
    private const TZ = 'Asia/Jakarta';

    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->with('user:id,name,email,role,company_id')
            ->latest('id')
            ->paginate((int) $request->input('per_page', 25) === 100 ? 100 : 25)
            ->withQueryString();

        // Nama tenant untuk kolom "Tenant"
        $companyIds = $logs->getCollection()->map->companyId()->filter()->unique()->values();
        $companyNames = $companyIds->isEmpty() ? collect() : Company::whereIn('id', $companyIds)->pluck('name', 'id');

        $todayStart = now(self::TZ)->startOfDay()->timezone(config('app.timezone'));
        $weekStart = now(self::TZ)->subDays(6)->startOfDay()->timezone(config('app.timezone'));
        $monthStart = now()->subDays(30);

        $stats = [
            'today'   => AuditLog::where('created_at', '>=', $todayStart)->count(),
            'week'    => AuditLog::where('created_at', '>=', $weekStart)->count(),
            'deletes' => AuditLog::where('created_at', '>=', $weekStart)->where('action', 'like', 'delete\_%')->count(),
            'total'   => AuditLog::count(),
        ];

        $topActions = AuditLog::where('created_at', '>=', $monthStart)
            ->select('action', DB::raw('count(*) as n'))->groupBy('action')->orderByDesc('n')->limit(8)->get();

        $topUsers = AuditLog::where('created_at', '>=', $monthStart)->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as n'))->groupBy('user_id')->orderByDesc('n')->limit(5)->get();
        $topUserNames = User::whereIn('id', $topUsers->pluck('user_id'))->pluck('name', 'id');

        return view('pages.superadmin.audit-logs.index', [
            'logs'         => $logs,
            'companyNames' => $companyNames,
            'stats'        => $stats,
            'topActions'   => $topActions,
            'topUsers'     => $topUsers,
            'topUserNames' => $topUserNames,
            'kinds'        => AuditLog::KINDS,
            'actions'      => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'actors'       => $this->actors(),
            'subjectTypes' => AuditLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'tenants'      => Company::orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function show($id)
    {
        $log = AuditLog::with('user')->findOrFail($id);

        $companyId = $log->companyId();
        $company = $companyId ? Company::find($companyId, ['id', 'name', 'type']) : null;

        // Log lain untuk data yang sama
        $related = collect();
        if ($log->subject_type && $log->subject_id) {
            $related = AuditLog::with('user:id,name')
                ->where('subject_type', $log->subject_type)->where('subject_id', $log->subject_id)
                ->whereKeyNot($log->id)->latest('id')->limit(20)->get();
        }

        // Aktivitas pelaku yang sama di sekitar waktu ini (±30 menit)
        $nearby = collect();
        if ($log->user_id) {
            $nearby = AuditLog::where('user_id', $log->user_id)->whereKeyNot($log->id)
                ->whereBetween('created_at', [$log->created_at->copy()->subMinutes(30), $log->created_at->copy()->addMinutes(30)])
                ->orderBy('id')->limit(20)->get();
        }

        $prev = AuditLog::where('id', '<', $log->id)->max('id');
        $next = AuditLog::where('id', '>', $log->id)->min('id');

        return view('pages.superadmin.audit-logs.show', compact('log', 'company', 'related', 'nearby', 'prev', 'next'));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('user:id,name,email')->orderBy('id');
        $count = (clone $query)->count();

        AuditLog::record('export_audit_logs', null, "Export audit log ({$count} baris)", [
            'filters' => array_filter($request->only(['search', 'kind', 'action', 'user_id', 'company_id', 'subject_type', 'subject_id', 'ip', 'from', 'to'])),
        ]);

        $companyNames = Company::pluck('name', 'id');

        return response()->streamDownload(function () use ($query, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Waktu (WIB)', 'Pelaku', 'Email', 'Aksi', 'Jenis Data', 'ID Data', 'Tenant', 'Deskripsi', 'IP', 'Meta'], ';');

            $query->chunkById(1000, function ($rows) use ($out, $companyNames) {
                foreach ($rows as $log) {
                    $cid = $log->companyId();
                    fputcsv($out, [
                        $log->id,
                        optional($log->created_at)->timezone(self::TZ)->format('Y-m-d H:i:s'),
                        $log->user->name ?? ($log->user_id ? '#' . $log->user_id . ' (dihapus)' : 'Sistem'),
                        $log->user->email ?? '',
                        $log->action,
                        $log->subjectName(),
                        $log->subject_id,
                        $cid ? ($companyNames[$cid] ?? '#' . $cid) : '',
                        $log->description,
                        $log->ip_address,
                        $log->meta ? json_encode($log->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                    ], ';');
                }
            });

            fclose($out);
        }, 'audit-log-' . now(self::TZ)->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // Helpers
    // =========================================================

    private function filtered(Request $request): Builder
    {
        $q = AuditLog::query();

        if ($request->filled('search')) {
            $s = '%' . addcslashes(trim($request->search), '%_\\') . '%';
            $q->where(fn($w) => $w->where('description', 'like', $s)->orWhere('action', 'like', $s));
        }

        if ($request->filled('kind')) {
            $q->where('action', 'like', addcslashes($request->kind, '%_\\') . '\_%');
        }

        if ($request->filled('action')) {
            $q->where('action', $request->action);
        }

        if ($request->input('user_id') === 'system') {
            $q->whereNull('user_id');
        } elseif ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('company_id')) {
            $cid = (int) $request->company_id;
            $q->where(fn($w) => $w->where(fn($x) => $x->where('subject_type', Company::class)->where('subject_id', $cid))
                ->orWhere('meta->company_id', $cid)
                ->orWhere('meta->company_id', (string) $cid));
        }

        if ($request->filled('subject_type')) {
            $q->where('subject_type', $request->subject_type);
        }
        if ($request->filled('subject_id')) {
            $q->where('subject_id', (int) $request->subject_id);
        }

        if ($request->filled('ip')) {
            $q->where('ip_address', 'like', addcslashes(trim($request->input('ip')), '%_\\') . '%');
        }

        foreach (['from' => 'startOfDay', 'to' => 'endOfDay'] as $key => $fn) {
            if ($request->filled($key)) {
                try {
                    $d = Carbon::createFromFormat('Y-m-d', $request->input($key), self::TZ)->{$fn}()->timezone(config('app.timezone'));
                    $q->where('created_at', $key === 'from' ? '>=' : '<=', $d);
                } catch (\Throwable) {
                    // tanggal tidak valid → abaikan
                }
            }
        }

        return $q;
    }

    /** Pelaku yang pernah tercatat di log. */
    private function actors()
    {
        $ids = AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        return User::whereIn('id', $ids)->orderBy('name')->get(['id', 'name', 'role', 'company_id']);
    }
}