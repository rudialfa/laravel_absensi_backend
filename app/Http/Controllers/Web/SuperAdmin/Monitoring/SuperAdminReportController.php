<?php

namespace App\Http\Controllers\Web\SuperAdmin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DailyReport;
use App\Models\MonthlyReport;
use App\Models\Note;
use App\Models\PerformanceScore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Laporan & Kinerja karyawan:
 * daily (laporan harian) · monthly (laporan bulanan) · scores (skor kinerja) · notes (catatan HR)
 * Penilaian tetap oleh HR. Superadmin memantau, export, dan menghapus data yang salah.
 */
class SuperAdminReportController extends Controller
{
    public const TABS = [
        'daily'   => 'Laporan Harian',
        'monthly' => 'Laporan Bulanan',
        'scores'  => 'Skor Kinerja',
        'notes'   => 'Catatan HR',
    ];

    public const MONTHLY_BADGE = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
    public const NOTE_BADGE    = ['warning' => 'danger', 'praise' => 'success', 'performance' => 'info', 'absence' => 'warning', 'general' => 'secondary'];

    // =========================================================
    // INDEX (?tab=...)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'daily';
        [$year, $month] = $this->period($request);

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'year'         => $year,
            'month'        => $month,
            'monthOptions' => $this->monthOptions(),
            'companies'    => Company::where('type', 'company')->orderBy('name')->get(['id', 'name']),
            'companyNames' => Company::pluck('name', 'id'),
        ];

        return view('pages.superadmin.monitoring.reports.index', $common + match ($tab) {
            'monthly' => $this->monthlyData($request, $year, $month),
            'scores'  => $this->scoresData($request, $year, $month),
            'notes'   => $this->notesData($request),
            default   => $this->dailyData($request, $year, $month),
        });
    }

    private function dailyData(Request $request, int $year, int $month): array
    {
        $query = $this->dailyQuery($request, $year, $month);

        // Kepatuhan isi laporan harian per perusahaan (bulan terpilih)
        $employees = User::where('role', 'employee')->where('is_active', true)->whereNotNull('company_id')
            ->select('company_id', DB::raw('count(*) as total'))->groupBy('company_id')->pluck('total', 'company_id');

        $compliance = DailyReport::whereYear('date', $year)->whereMonth('date', $month)
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->select('company_id')
            ->selectRaw('count(*) as reports, count(distinct user_id) as reporters, sum(is_achieved = 1) as achieved, sum(achievement is not null) as with_achievement')
            ->groupBy('company_id')
            ->get()
            ->map(fn($r) => (object) [
                'company_id' => $r->company_id,
                'employees'  => (int) ($employees[$r->company_id] ?? 0),
                'reporters'  => (int) $r->reporters,
                'reports'    => (int) $r->reports,
                'achieved'   => (int) $r->achieved,
                'rate'       => $r->with_achievement > 0 ? round($r->achieved / $r->with_achievement * 100) : null,
            ]);

        return [
            'items'      => $query->with('user:id,name,department')->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString(),
            'compliance' => $compliance,
        ];
    }

    private function monthlyData(Request $request, int $year, int $month): array
    {
        $query = $this->monthlyQuery($request, $year, $month);

        return [
            'items'         => (clone $query)->with(['user:id,name,department', 'approver:id,name'])
                ->orderByRaw("status = 'submitted' DESC")->orderByDesc('year')->orderByDesc('month')->paginate(25)->withQueryString(),
            'statusCounts'  => (clone $query)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'statusLabels'  => MonthlyReport::STATUS_LABELS,
            'badge'         => self::MONTHLY_BADGE,
        ];
    }

    private function scoresData(Request $request, int $year, int $month): array
    {
        $query = PerformanceScore::query()
            ->where('year', $year)->where('month', $month)
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->search, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%')));

        return [
            'items'   => (clone $query)->with('user:id,name,department')->orderByDesc('final_score')->paginate(25)->withQueryString(),
            'average' => (clone $query)->selectRaw('avg(final_score) as score, avg(achievement_rate) as rate, count(*) as total')->first(),
        ];
    }

    private function notesData(Request $request): array
    {
        $query = $this->notesQuery($request);

        return [
            'items'      => (clone $query)->with(['user:id,name,department', 'creator:id,name'])->latest()->paginate(25)->withQueryString(),
            'typeCounts' => (clone $query)->select('type', DB::raw('count(*) as total'))->groupBy('type')->pluck('total', 'type'),
            'noteTypes'  => Note::TYPE_LABELS,
            'badge'      => self::NOTE_BADGE,
        ];
    }

    // =========================================================
    // SHOW (/{tab}/{id}) — daily | monthly | notes
    // =========================================================
    public function show(string $tab, $id)
    {
        $item = match ($tab) {
            'daily'   => DailyReport::with('user:id,name,department,position')->findOrFail($id),
            'monthly' => MonthlyReport::with(['user:id,name,department,position', 'approver:id,name'])->findOrFail($id),
            'notes'   => Note::with(['user:id,name,department,position', 'creator:id,name'])->findOrFail($id),
            default   => abort(404),
        };

        $score = $tab === 'monthly'
            ? PerformanceScore::where('user_id', $item->user_id)->where('year', $item->year)->where('month', $item->month)->first()
            : null;

        return view('pages.superadmin.monitoring.reports.show', [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'item'         => $item,
            'company'      => Company::find($item->company_id),
            'score'        => $score,
            'statusLabels' => MonthlyReport::STATUS_LABELS,
            'monthlyBadge' => self::MONTHLY_BADGE,
            'noteTypes'    => Note::TYPE_LABELS,
            'noteBadge'    => self::NOTE_BADGE,
            'file'         => $this->fileOf($tab, $item),
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $item = match ($tab) {
            'daily'   => DailyReport::with('user:id,name')->findOrFail($id),
            'monthly' => MonthlyReport::with('user:id,name')->findOrFail($id),
            'notes'   => Note::with('user:id,name')->findOrFail($id),
            default   => abort(404),
        };

        $snapshot = $item->getAttributes();
        $file     = $this->fileOf($tab, $item);

        $item->delete();

        if ($file && !str_starts_with($file, 'http') && File::exists(public_path($file))) {
            File::delete(public_path($file));
        }

        AuditLog::record("delete_report_{$tab}", null, self::TABS[$tab] . " {$item->user?->name} dihapus. Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return redirect()
            ->route('superadmin.monitoring.reports.index', ['tab' => $tab, 'company_id' => $item->company_id])
            ->with('success', self::TABS[$tab] . ' dihapus.');
    }

    // =========================================================
    // EXPORT CSV (tab aktif)
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'daily';
        [$year, $month] = $this->period($request);
        $names = Company::pluck('name', 'id');

        AuditLog::record("export_reports_{$tab}", null, 'Export ' . self::TABS[$tab], ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $year, $month, $names) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $write = fn(array $row) => fputcsv($out, $row, ';');

            switch ($tab) {
                case 'monthly':
                    $write(['Perusahaan', 'Karyawan', 'Periode', 'Target', 'Pencapaian', 'Masalah', 'Solusi', 'Status', 'Skor', 'Direview oleh']);
                    $this->monthlyQuery($request, $year, $month)->with(['user:id,name', 'approver:id,name'])->chunk(500, function ($rows) use ($write, $names) {
                        foreach ($rows as $r) {
                            $write([
                                $names[$r->company_id] ?? '-',
                                $r->user->name ?? '-',
                                $r->period_label,
                                $r->target,
                                $r->achievement,
                                $r->problem,
                                $r->solution,
                                MonthlyReport::STATUS_LABELS[$r->status] ?? $r->status,
                                $r->score,
                                $r->approver->name ?? ''
                            ]);
                        }
                    });
                    break;

                case 'scores':
                    $write(['Perusahaan', 'Karyawan', 'Tahun', 'Bulan', 'Total target', 'Tercapai', 'Persentase', 'Skor akhir']);
                    PerformanceScore::with('user:id,name')->where('year', $year)->where('month', $month)
                        ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                        ->orderBy('company_id')->orderByDesc('final_score')
                        ->chunk(500, function ($rows) use ($write, $names) {
                            foreach ($rows as $s) {
                                $write([$names[$s->company_id] ?? '-', $s->user->name ?? '-', $s->year, $s->month, $s->total_targets, $s->targets_achieved, $s->achievement_rate, $s->final_score]);
                            }
                        });
                    break;

                case 'notes':
                    $write(['Perusahaan', 'Karyawan', 'Jenis', 'Judul', 'Isi', 'Dibuat oleh', 'Dibaca', 'Tanggal']);
                    $this->notesQuery($request)->with(['user:id,name', 'creator:id,name'])->chunk(500, function ($rows) use ($write, $names) {
                        foreach ($rows as $n) {
                            $write([
                                $names[$n->company_id] ?? '-',
                                $n->user->name ?? '-',
                                Note::TYPE_LABELS[$n->type] ?? $n->type,
                                $n->title,
                                $n->note,
                                $n->creator->name ?? '',
                                $n->is_read ? 'Ya' : 'Belum',
                                optional($n->created_at)->format('Y-m-d H:i')
                            ]);
                        }
                    });
                    break;

                default:
                    $write(['Perusahaan', 'Karyawan', 'Tanggal', 'Target', 'Pencapaian', 'Tercapai', 'Alasan tidak tercapai']);
                    $this->dailyQuery($request, $year, $month)->with('user:id,name')->orderBy('date')->chunk(500, function ($rows) use ($write, $names) {
                        foreach ($rows as $d) {
                            $write([
                                $names[$d->company_id] ?? '-',
                                $d->user->name ?? '-',
                                optional($d->date)->format('Y-m-d'),
                                $d->target,
                                $d->achievement,
                                $d->achievement === null ? 'Belum diisi' : ($d->is_achieved ? 'Ya' : 'Tidak'),
                                $d->reason_not_achieved
                            ]);
                        }
                    });
            }

            fclose($out);
        }, "laporan-{$tab}-{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // HELPER
    // =========================================================

    /** @return array{0:int,1:int} periode Y-m dari ?period=, default bulan ini */
    private function period(Request $request): array
    {
        $p = $request->filled('period') ? Carbon::createFromFormat('Y-m', $request->period) : now();

        return [(int) $p->year, (int) $p->month];
    }

    private function monthOptions(): array
    {
        $opts = [];
        for ($i = 0; $i < 12; $i++) {
            $m = now()->startOfMonth()->subMonthsNoOverflow($i);
            $opts[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return $opts;
    }

    private function search(Request $request): ?string
    {
        return $request->filled('search') ? '%' . addcslashes(trim($request->search), '%_\\') . '%' : null;
    }

    private function dailyQuery(Request $request, int $year, int $month)
    {
        $like = $this->search($request);

        return DailyReport::query()
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->date, fn($q, $d) => $q->whereDate('date', $d))
            ->when($request->result, fn($q, $r) => match ($r) {
                'achieved'     => $q->where('is_achieved', true),
                'not_achieved' => $q->whereNotNull('achievement')->where('is_achieved', false),
                'empty'        => $q->whereNull('achievement'),
                default        => $q,
            })
            ->when($like, fn($q) => $q->where(fn($w) => $w->whereHas('user', fn($u) => $u->where('name', 'like', $like))->orWhere('target', 'like', $like)));
    }

    private function monthlyQuery(Request $request, int $year, int $month)
    {
        $like = $this->search($request);

        return MonthlyReport::query()
            ->where('year', $year)->where('month', $month)
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($like, fn($q) => $q->whereHas('user', fn($u) => $u->where('name', 'like', $like)));
    }

    private function notesQuery(Request $request)
    {
        $like = $this->search($request);

        return Note::query()
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->filled('read'), fn($q) => $q->where('is_read', $request->read === '1'))
            ->when($like, fn($q) => $q->where(fn($w) => $w->whereHas('user', fn($u) => $u->where('name', 'like', $like))->orWhere('title', 'like', $like)));
    }

    private function fileOf(string $tab, $item): ?string
    {
        return match ($tab) {
            'daily', 'monthly' => $item->attachment,
            'notes'            => $item->image_url,
            default            => null,
        };
    }
}
