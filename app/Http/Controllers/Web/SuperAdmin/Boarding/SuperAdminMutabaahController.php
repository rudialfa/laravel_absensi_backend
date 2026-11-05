<?php

namespace App\Http\Controllers\Web\SuperAdmin\Boarding;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\SuperAdmin\School\SuperAdminSchoolClassController;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\MutabaahYaumiyah;
use App\Models\Student;
use App\Models\TahfidzSetoran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Mutaba'ah & Tahfidz.
 *
 * Dua sumber data yang berbeda:
 *  - mutabaah_yaumiyahs : catatan ngaji harian pesantren (Iqro/Al-Qur'an, per sesi pagi/sore,
 *                         nilai A+..D-, paraf ustadz). Santri & ustadz = akun users.
 *  - tahfidz_setorans   : setoran hafalan murid sekolah/pondok (juz, surah, ayat, ziyadah/murajaah).
 *
 * Diisi ustadz/guru lewat aplikasi. Superadmin: pantau, rekap progres, export,
 * dan hapus catatan yang salah/ganda (wajib alasan, tercatat di Audit Log).
 */
class SuperAdminMutabaahController extends Controller
{
    public const TABS = [
        'mutabaah' => "Mutaba'ah Ngaji",
        'progress' => 'Posisi Bacaan Santri',
        'tahfidz'  => 'Setoran Tahfidz',
        'hafalan'  => 'Rekap Hafalan',
    ];

    public const KITAB = ['iqro' => 'Iqro', 'quran' => "Al-Qur'an"];
    public const SESI = ['pagi' => 'Pagi', 'sore' => 'Sore'];
    public const NILAI_BADGE = ['green' => 'success', 'blue' => 'primary', 'yellow' => 'warning', 'red' => 'danger'];

    public const TAHFIDZ_JENIS = ['ziyadah' => 'Ziyadah (hafalan baru)', 'murajaah' => "Muraja'ah (mengulang)"];
    public const TAHFIDZ_STATUS = ['lancar' => 'Lancar', 'kurang_lancar' => 'Kurang Lancar', 'mengulang' => 'Mengulang'];
    public const TAHFIDZ_BADGE = ['lancar' => 'success', 'kurang_lancar' => 'warning', 'mengulang' => 'danger'];

    /** Santri dianggap "lama tidak ngaji / setor" setelah sekian hari */
    public const IDLE_DAYS = 7;

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'mutabaah';
        [$from, $to] = $this->range($request);

        $common = [
            'tab'           => $tab,
            'tabs'          => self::TABS,
            'from'          => $from,
            'to'            => $to,
            'schools'       => Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name', 'type']),
            'companyNames'  => Company::pluck('name', 'id'),
            'teacherOptions' => $request->company_id
                ? User::where('company_id', $request->company_id)->whereIn('role', ['ustadz', 'guru'])->orderBy('name')->get(['id', 'name'])
                : collect(),
            'kitab'         => self::KITAB,
            'sesi'          => self::SESI,
            'nilaiList'     => MutabaahYaumiyah::SEMUA_NILAI,
            'nilaiBadge'    => self::NILAI_BADGE,
            'tahfidzJenis'  => self::TAHFIDZ_JENIS,
            'tahfidzStatus' => self::TAHFIDZ_STATUS,
            'tahfidzBadge'  => self::TAHFIDZ_BADGE,
        ];

        return match ($tab) {
            'progress' => $this->progressTab($request, $common),
            'tahfidz'  => $this->tahfidzTab($request, $common),
            'hafalan'  => $this->hafalanTab($request, $common),
            default    => $this->mutabaahTab($request, $common),
        };
    }

    private function mutabaahTab(Request $request, array $common)
    {
        $query = $this->mutabaahQuery($request, $common['from'], $common['to']);

        $items = (clone $query)
            ->with(['santri:id,name,company_id', 'ustadz:id,name', 'penandatangan:id,name'])
            ->orderByDesc('tanggal')->orderByRaw("sesi = 'sore' DESC")->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $summary = $this->mutabaahQuery($request, $common['from'], $common['to'], withStatus: false)->toBase()
            ->selectRaw('count(*) as total, sum(is_lanjut = 1) as lanjut, sum(signed_by is null) as unsigned,
                count(distinct santri_id) as santri, count(distinct ustadz_id) as ustadz, count(distinct tanggal) as days')
            ->first();

        $byNilai = $this->mutabaahQuery($request, $common['from'], $common['to'], withStatus: false)->toBase()
            ->selectRaw('keterangan, count(*) as total')->groupBy('keterangan')->pluck('total', 'keterangan');

        return view('pages.superadmin.boarding.mutabaah.index', $common + compact('items', 'summary', 'byNilai'));
    }

    /** Posisi bacaan terakhir tiap santri (record mutaba'ah terbaru per santri) */
    private function progressTab(Request $request, array $common)
    {
        $from30 = today()->subDays(29)->toDateString();

        $latestIds = MutabaahYaumiyah::query()
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->selectRaw('max(id)')
            ->groupBy('santri_id');

        $items = MutabaahYaumiyah::query()
            ->whereIn('id', $latestIds)
            ->with(['santri:id,name,company_id,is_active', 'ustadz:id,name'])
            ->addSelect([
                'sessions_30' => MutabaahYaumiyah::from('mutabaah_yaumiyahs as m2')->selectRaw('count(*)')
                    ->whereColumn('m2.santri_id', 'mutabaah_yaumiyahs.santri_id')->where('m2.tanggal', '>=', $from30),
                'lanjut_30'   => MutabaahYaumiyah::from('mutabaah_yaumiyahs as m3')->selectRaw('sum(m3.is_lanjut = 1)')
                    ->whereColumn('m3.santri_id', 'mutabaah_yaumiyahs.santri_id')->where('m3.tanggal', '>=', $from30),
                'total_all'   => MutabaahYaumiyah::from('mutabaah_yaumiyahs as m4')->selectRaw('count(*)')
                    ->whereColumn('m4.santri_id', 'mutabaah_yaumiyahs.santri_id'),
            ])
            ->addSelect('mutabaah_yaumiyahs.*')
            ->when($request->kitab, fn($q, $k) => $q->where('kitab', $k))
            ->when($request->status === 'idle', fn($q) => $q->where('tanggal', '<', today()->subDays(self::IDLE_DAYS)->toDateString()))
            ->when($request->search, fn($q, $s) => $q->whereIn('santri_id', User::where('name', 'like', '%' . addcslashes($s, '%_\\') . '%')->select('id')))
            ->orderByRaw("kitab = 'quran' DESC")->orderByDesc('jilid')->orderByDesc('halaman_dari')
            ->paginate(30)->withQueryString();

        // Santri (role santri) yang belum pernah tercatat mutaba'ah sama sekali
        $neverCount = User::where('role', 'santri')
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->where('is_active', true)
            ->whereNotExists(fn($e) => $e->from('mutabaah_yaumiyahs')->whereColumn('mutabaah_yaumiyahs.santri_id', 'users.id'))
            ->count();

        return view('pages.superadmin.boarding.mutabaah.index', $common + compact('items', 'neverCount'));
    }

    private function tahfidzTab(Request $request, array $common)
    {
        $query = $this->tahfidzQuery($request, $common['from'], $common['to']);

        $items = (clone $query)
            ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'deleted_at')->with('classRoom:id,name'), 'guru:id,name'])
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $summary = $this->tahfidzQuery($request, $common['from'], $common['to'], withStatus: false)->toBase()
            ->selectRaw("count(*) as total, sum(jenis = 'ziyadah') as ziyadah, sum(jenis = 'murajaah') as murajaah,
                sum(status = 'lancar') as lancar, count(distinct student_id) as students,
                coalesce(sum(case when jenis = 'ziyadah' then ayat_akhir - ayat_awal + 1 end), 0) as ayat_baru")
            ->first();

        return view('pages.superadmin.boarding.mutabaah.index', $common + compact('items', 'summary'));
    }

    /** Rekap hafalan per murid: juz tertinggi, total setoran, setoran terakhir */
    private function hafalanTab(Request $request, array $common)
    {
        $from30 = today()->subDays(29)->toDateString();

        $items = TahfidzSetoran::query()
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->search, fn($q, $s) => $q->whereIn('student_id', $this->studentSearch($s)))
            ->selectRaw("student_id, company_id,
                count(*) as total,
                sum(jenis = 'ziyadah') as ziyadah,
                sum(jenis = 'murajaah') as murajaah,
                sum(tanggal >= ?) as last30,
                sum(status = 'lancar') as lancar,
                max(juz) as max_juz,
                count(distinct juz) as juz_count,
                max(tanggal) as last_date", [$from30])
            ->groupBy('student_id', 'company_id')
            ->when($request->status === 'idle', fn($q) => $q->havingRaw('max(tanggal) < ?', [today()->subDays(self::IDLE_DAYS)->toDateString()]))
            ->orderByDesc('max_juz')->orderByDesc('total')
            ->paginate(30)->withQueryString();

        $students = Student::withTrashed()->with('classRoom:id,name')->whereIn('id', $items->pluck('student_id'))->get(['id', 'name', 'nis', 'class_id', 'deleted_at'])->keyBy('id');

        return view('pages.superadmin.boarding.mutabaah.index', $common + compact('items', 'students'));
    }

    // =========================================================
    // DETAIL MUTABA'AH 1 SANTRI
    // =========================================================
    public function santri($userId)
    {
        $santri = User::with('company:id,name')->findOrFail($userId);

        $records = MutabaahYaumiyah::where('santri_id', $santri->id)
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderByDesc('tanggal')->orderByRaw("sesi = 'sore' DESC")
            ->paginate(40);

        $stats = MutabaahYaumiyah::where('santri_id', $santri->id)->toBase()
            ->selectRaw('count(*) as total, sum(is_lanjut = 1) as lanjut, sum(signed_by is null) as unsigned, min(tanggal) as first_date, max(tanggal) as last_date')
            ->first();

        // Perkembangan per jilid (berapa sesi dihabiskan di tiap jilid)
        $perJilid = MutabaahYaumiyah::where('santri_id', $santri->id)->toBase()
            ->selectRaw('kitab, jilid, count(*) as sessions, sum(is_lanjut = 1) as lanjut, min(tanggal) as start, max(tanggal) as end, max(coalesce(halaman_sampai, halaman_dari)) as max_hal')
            ->groupBy('kitab', 'jilid')
            ->orderByRaw("kitab = 'quran'")->orderBy('jilid')
            ->get();

        return view('pages.superadmin.boarding.mutabaah.santri', [
            'santri'     => $santri,
            'records'    => $records,
            'stats'      => $stats,
            'perJilid'   => $perJilid,
            'latest'     => MutabaahYaumiyah::where('santri_id', $santri->id)->latest('tanggal')->latest('id')->first(),
            'kitab'      => self::KITAB,
            'sesi'       => self::SESI,
            'nilaiBadge' => self::NILAI_BADGE,
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        if ($tab === 'tahfidz') {
            $item = TahfidzSetoran::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
            $snapshot = $item->getAttributes();
            $item->delete();

            AuditLog::record(
                'delete_tahfidz_setoran',
                null,
                "Setoran tahfidz {$item->surah} ayat {$item->ayat_awal}-{$item->ayat_akhir} murid {$item->student?->name} tanggal " . optional($item->tanggal)->format('Y-m-d') . " (tenant #{$item->company_id}) dihapus. Alasan: {$data['reason']}",
                $snapshot + ['reason' => $data['reason']]
            );

            return back()->with('success', 'Setoran tahfidz dihapus.');
        }

        abort_unless($tab === 'mutabaah', 404);

        $item = MutabaahYaumiyah::with('santri:id,name')->findOrFail($id);
        $snapshot = $item->getAttributes();
        $item->delete();

        AuditLog::record(
            'delete_mutabaah',
            null,
            "Mutaba'ah {$item->label_posisi} santri {$item->santri?->name} tanggal " . optional($item->tanggal)->format('Y-m-d') . " sesi {$item->sesi} (tenant #{$item->company_id}) dihapus. Alasan: {$data['reason']}",
            $snapshot + ['reason' => $data['reason']]
        );

        return back()->with('success', "Catatan mutaba'ah dihapus. Posisi halaman berikutnya santri dihitung dari catatan terakhir yang tersisa.");
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = in_array($request->tab, ['mutabaah', 'tahfidz'], true) ? $request->tab : 'mutabaah';
        [$from, $to] = $this->range($request);
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $from, $to, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($tab === 'tahfidz') {
                fputcsv($out, ['Sekolah', 'Tanggal', 'Murid', 'NIS', 'Kelas', 'Juz', 'Surah', 'Ayat awal', 'Ayat akhir', 'Jenis', 'Status', 'Guru', 'Catatan'], ';');
                $this->tahfidzQuery($request, $from, $to)
                    ->with(['student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'), 'guru:id,name'])
                    ->orderBy('id')
                    ->chunk(500, function ($rows) use ($out, $companyNames) {
                        foreach ($rows as $t) {
                            fputcsv($out, [
                                $companyNames[$t->company_id] ?? '-',
                                optional($t->tanggal)->format('Y-m-d'),
                                $t->student->name ?? '',
                                $t->student->nis ?? '',
                                $t->student->classRoom->name ?? '',
                                $t->juz,
                                $t->surah,
                                $t->ayat_awal,
                                $t->ayat_akhir,
                                self::TAHFIDZ_JENIS[$t->jenis] ?? $t->jenis,
                                self::TAHFIDZ_STATUS[$t->status] ?? $t->status,
                                $t->guru->name ?? '',
                                $t->catatan,
                            ], ';');
                        }
                    });
            } else {
                fputcsv($out, ['Pesantren', 'Tanggal', 'Sesi', 'Santri', 'Kitab', 'Jilid', 'Halaman dari', 'Halaman sampai', 'Nilai', 'Lanjut/Ulang', 'Ustadz', 'Paraf', 'Catatan'], ';');
                $this->mutabaahQuery($request, $from, $to)
                    ->with(['santri:id,name', 'ustadz:id,name', 'penandatangan:id,name'])
                    ->orderBy('id')
                    ->chunk(500, function ($rows) use ($out, $companyNames) {
                        foreach ($rows as $m) {
                            fputcsv($out, [
                                $companyNames[$m->company_id] ?? '-',
                                optional($m->tanggal)->format('Y-m-d'),
                                self::SESI[$m->sesi] ?? $m->sesi,
                                $m->santri->name ?? '',
                                self::KITAB[$m->kitab] ?? $m->kitab,
                                $m->jilid,
                                $m->halaman_dari,
                                $m->halaman_sampai,
                                $m->keterangan,
                                $m->is_lanjut ? 'Lanjut' : 'Ulang',
                                $m->ustadz->name ?? '',
                                $m->penandatangan->name ?? 'Belum',
                                $m->catatan,
                            ], ';');
                        }
                    });
            }

            fclose($out);
        }, $tab . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY & HELPER
    // =========================================================
    private function schoolIds()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id');
    }

    private function mutabaahQuery(Request $request, Carbon $from, Carbon $to, bool $withStatus = true)
    {
        return MutabaahYaumiyah::query()
            ->whereIn('company_id', $this->schoolIds())
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->teacher_id, fn($q, $id) => $q->where('ustadz_id', $id))
            ->when($request->kitab, fn($q, $k) => $q->where('kitab', $k))
            ->when($request->sesi, fn($q, $s) => $q->where('sesi', $s))
            ->when($withStatus && $request->nilai, fn($q) => $q->where('keterangan', $request->nilai))
            ->when($withStatus && $request->status === 'lanjut', fn($q) => $q->where('is_lanjut', true))
            ->when($withStatus && $request->status === 'ulang', fn($q) => $q->where('is_lanjut', false))
            ->when($withStatus && $request->status === 'unsigned', fn($q) => $q->whereNull('signed_by'))
            ->when($request->search, fn($q, $s) => $q->whereIn('santri_id', User::where('name', 'like', '%' . addcslashes($s, '%_\\') . '%')->select('id')));
    }

    private function tahfidzQuery(Request $request, Carbon $from, Carbon $to, bool $withStatus = true)
    {
        return TahfidzSetoran::query()
            ->whereIn('company_id', $this->schoolIds())
            ->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()])
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->teacher_id, fn($q, $id) => $q->where('guru_id', $id))
            ->when($request->juz, fn($q, $j) => $q->where('juz', $j))
            ->when($request->jenis, fn($q, $j) => $q->where('jenis', $j))
            ->when($withStatus && $request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q, $s) => $q->where(fn($w) => $w->whereIn('student_id', $this->studentSearch($s))->orWhere('surah', 'like', '%' . addcslashes($s, '%_\\') . '%')));
    }

    private function studentSearch(string $s)
    {
        $like = '%' . addcslashes($s, '%_\\') . '%';

        return Student::withTrashed()->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id');
    }

    /** Default 30 hari terakhir (90 hari kalau mencari santri tertentu) */
    private function range(Request $request): array
    {
        $default = $request->search ? 89 : 29;

        try {
            $to = $request->date_to ? Carbon::parse($request->date_to)->startOfDay() : today();
            $from = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : $to->copy()->subDays($default);
        } catch (\Throwable) {
            $to = today();
            $from = $to->copy()->subDays($default);
        }

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }
}
