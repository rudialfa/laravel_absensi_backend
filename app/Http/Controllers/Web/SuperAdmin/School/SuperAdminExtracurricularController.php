<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\ExtracurricularMember;
use App\Models\Student;
use App\Models\StudentAchievement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Ekstrakurikuler (anggota & absensi ekskul) dan Prestasi Murid semua sekolah.
 * Tabel: extracurriculars, extracurricular_members, extracurricular_attendances, student_achievements.
 *
 * Dikelola Admin Sekolah & guru pembina. Superadmin: pantau, export, dan hapus data salah
 * (wajib alasan, sertifikat ikut terhapus, tercatat di Audit Log).
 */
class SuperAdminExtracurricularController extends Controller
{
    public const TABS = [
        'extracurriculars' => 'Ekstrakurikuler',
        'achievements'     => 'Prestasi Murid',
    ];

    public const DAYS = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu'];

    public const LEVELS = [
        'sekolah'       => 'Sekolah',
        'kecamatan'     => 'Kecamatan',
        'kabupaten'     => 'Kabupaten/Kota',
        'provinsi'      => 'Provinsi',
        'nasional'      => 'Nasional',
        'internasional' => 'Internasional',
    ];
    public const LEVEL_BADGE = [
        'sekolah'       => 'light',
        'kecamatan'     => 'secondary',
        'kabupaten'     => 'info',
        'provinsi'      => 'primary',
        'nasional'      => 'warning',
        'internasional' => 'danger',
    ];

    public const ATTENDANCE_STATUS = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
    public const ATTENDANCE_BADGE = ['hadir' => 'success', 'izin' => 'info', 'sakit' => 'primary', 'alpa' => 'danger'];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'extracurriculars';

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'schools'      => Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name']),
            'companyNames' => Company::pluck('name', 'id'),
            'days'         => self::DAYS,
            'levels'       => self::LEVELS,
            'levelBadge'   => self::LEVEL_BADGE,
            'ekskulOptions' => $request->company_id
                ? Extracurricular::where('company_id', $request->company_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'classOptions' => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->where('is_active', true)->orderBy('grade_level')->orderBy('name')->get(['id', 'name'])
                : collect(),
        ];

        if ($tab === 'achievements') {
            $query = $this->achievementQuery($request);

            return view('pages.superadmin.school.extracurriculars.index', $common + [
                'items'   => (clone $query)->with([
                    'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'deleted_at')->with('classRoom:id,name'),
                    'extracurricular:id,name',
                    'recordedBy:id,name',
                ])->latest('achieved_at')->paginate(30)->withQueryString(),
                'byLevel' => $this->achievementQuery($request, withLevel: false)->toBase()
                    ->selectRaw('level, count(*) as total')->groupBy('level')->pluck('total', 'level'),
            ]);
        }

        $from = today()->subDays(29)->toDateString();
        $query = $this->ekskulQuery($request);

        $items = (clone $query)
            ->with('pembina:id,name,is_active,company_id')
            ->withCount([
                'activeMembers',
                'attendances as att_30'       => fn($q) => $q->where('tanggal', '>=', $from),
                'attendances as hadir_30'     => fn($q) => $q->where('tanggal', '>=', $from)->where('status', 'hadir'),
            ])
            ->addSelect([
                'sessions_30'  => ExtracurricularAttendance::selectRaw('count(distinct tanggal)')
                    ->whereColumn('extracurricular_attendances.extracurricular_id', 'extracurriculars.id')->where('tanggal', '>=', $from),
                'last_session' => ExtracurricularAttendance::selectRaw('max(tanggal)')
                    ->whereColumn('extracurricular_attendances.extracurricular_id', 'extracurriculars.id'),
                'achievements_count' => StudentAchievement::selectRaw('count(*)')
                    ->whereColumn('student_achievements.extracurricular_id', 'extracurriculars.id'),
            ])
            ->orderBy('company_id')->orderByDesc('is_active')->orderBy('name')
            ->paginate(30)->withQueryString();

        $base = $this->ekskulQuery($request, withStatus: false);
        $summary = [
            'total'       => (clone $base)->count(),
            'active'      => (clone $base)->where('is_active', true)->count(),
            'members'     => ExtracurricularMember::whereIn('extracurricular_id', (clone $base)->select('id'))->where('is_active', true)->count(),
            'no_pembina'  => (clone $base)->where('is_active', true)->whereNull('pembina_id')->count(),
            'idle'        => (clone $base)->where('is_active', true)
                ->whereNotExists(fn($e) => $e->from('extracurricular_attendances')->whereColumn('extracurricular_attendances.extracurricular_id', 'extracurriculars.id')->where('tanggal', '>=', $from))
                ->count(),
        ];

        return view('pages.superadmin.school.extracurriculars.index', $common + ['items' => $items, 'summary' => $summary]);
    }

    // =========================================================
    // DETAIL EKSKUL
    // =========================================================
    public function show($id)
    {
        $ekskul = Extracurricular::with(['company:id,name', 'pembina:id,name,phone,email,is_active,company_id'])->findOrFail($id);

        $members = ExtracurricularMember::where('extracurricular_id', $ekskul->id)
            ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'is_active', 'deleted_at')->with('classRoom:id,name')])
            ->orderByDesc('is_active')->orderBy('joined_at')
            ->get();

        // 10 pertemuan terakhir + rekap per tanggal
        $sessionDates = ExtracurricularAttendance::where('extracurricular_id', $ekskul->id)
            ->select('tanggal')->distinct()->orderByDesc('tanggal')->limit(10)->pluck('tanggal')
            ->map(fn($d) => \Carbon\Carbon::parse($d)->toDateString())->sort()->values();

        $grid = ExtracurricularAttendance::where('extracurricular_id', $ekskul->id)
            ->whereIn('tanggal', $sessionDates)
            ->get(['student_id', 'tanggal', 'status'])
            ->groupBy('student_id')
            ->map(fn($rows) => $rows->keyBy(fn($r) => $r->tanggal->toDateString())->map->status);

        // Rekap kehadiran per anggota (seluruh waktu)
        $memberRecap = ExtracurricularAttendance::where('extracurricular_id', $ekskul->id)
            ->selectRaw("student_id, count(*) as total, sum(status = 'hadir') as hadir")
            ->groupBy('student_id')->get()->keyBy('student_id');

        return view('pages.superadmin.school.extracurriculars.show', [
            'ekskul'        => $ekskul,
            'members'       => $members,
            'sessionDates'  => $sessionDates,
            'grid'          => $grid,
            'memberRecap'   => $memberRecap,
            'totalSessions' => ExtracurricularAttendance::where('extracurricular_id', $ekskul->id)->distinct()->count('tanggal'),
            'achievements'  => StudentAchievement::where('extracurricular_id', $ekskul->id)
                ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->latest('achieved_at')->get(),
            'days'          => self::DAYS,
            'levels'        => self::LEVELS,
            'levelBadge'    => self::LEVEL_BADGE,
            'attStatus'     => self::ATTENDANCE_STATUS,
            'attBadge'      => self::ATTENDANCE_BADGE,
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);
        $reason = $data['reason'];

        if ($tab === 'achievements') {
            $item = StudentAchievement::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
            $snapshot = $item->getAttributes();
            $file = $item->certificate_path;

            $item->delete();
            $this->deleteFile($file);

            AuditLog::record('delete_student_achievement', null, "Prestasi \"{$item->title}\" murid {$item->student?->name} (tenant #{$item->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

            return redirect()->route('superadmin.school.extracurriculars.index', ['tab' => 'achievements', 'company_id' => $item->company_id])
                ->with('success', 'Prestasi dihapus. Sertifikat ikut terhapus.');
        }

        abort_unless($tab === 'extracurriculars', 404);

        $ekskul = Extracurricular::withCount(['members', 'attendances'])->findOrFail($id);
        $snapshot = $ekskul->getAttributes() + [
            'members'     => ExtracurricularMember::where('extracurricular_id', $ekskul->id)->get(['student_id', 'joined_at', 'is_active'])->toArray(),
            'attendances_count' => $ekskul->attendances_count,
        ];

        // Anggota & absensi ekskul ikut terhapus (cascade); prestasi tetap ada (extracurricular_id jadi kosong)
        DB::transaction(fn() => $ekskul->delete());

        AuditLog::record(
            'delete_extracurricular',
            null,
            "Ekskul {$ekskul->name} (tenant #{$ekskul->company_id}, {$ekskul->members_count} anggota, {$ekskul->attendances_count} absensi) dihapus. Alasan: {$reason}",
            $snapshot + ['reason' => $reason]
        );

        return redirect()->route('superadmin.school.extracurriculars.index', ['company_id' => $ekskul->company_id])
            ->with('success', "Ekskul {$ekskul->name} dihapus beserta {$ekskul->members_count} anggota dan {$ekskul->attendances_count} data absensinya.");
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'extracurriculars';
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($tab === 'achievements') {
                fputcsv($out, ['Sekolah', 'Tanggal', 'Murid', 'NIS', 'Kelas', 'Prestasi', 'Tingkat', 'Peringkat', 'Ekskul', 'Sertifikat', 'Dicatat oleh'], ';');
                $this->achievementQuery($request)
                    ->with(['student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'), 'extracurricular:id,name', 'recordedBy:id,name'])
                    ->orderBy('id')
                    ->chunk(500, function ($rows) use ($out, $companyNames) {
                        foreach ($rows as $a) {
                            fputcsv($out, [
                                $companyNames[$a->company_id] ?? '-',
                                optional($a->achieved_at)->format('Y-m-d'),
                                $a->student->name ?? '',
                                $a->student->nis ?? '',
                                $a->student->classRoom->name ?? '',
                                $a->title,
                                self::LEVELS[$a->level] ?? $a->level,
                                $a->rank,
                                $a->extracurricular->name ?? '',
                                $a->certificate_path ? 'Ada' : '',
                                $a->recordedBy->name ?? '',
                            ], ';');
                        }
                    });
            } else {
                fputcsv($out, ['Sekolah', 'Ekskul', 'Pembina', 'Hari', 'Jam', 'Anggota aktif', 'Status', 'Keterangan'], ';');
                $this->ekskulQuery($request)->with('pembina:id,name')->withCount('activeMembers')->orderBy('company_id')->orderBy('name')
                    ->each(function ($e) use ($out, $companyNames) {
                        fputcsv($out, [
                            $companyNames[$e->company_id] ?? '-',
                            $e->name,
                            $e->pembina->name ?? '',
                            self::DAYS[$e->hari] ?? '',
                            $e->jam_mulai ? substr($e->jam_mulai, 0, 5) . '–' . substr((string) $e->jam_selesai, 0, 5) : '',
                            $e->active_members_count,
                            $e->is_active ? 'Aktif' : 'Nonaktif',
                            $e->description,
                        ], ';');
                    });
            }

            fclose($out);
        }, $tab . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY & HELPER
    // =========================================================
    private function ekskulQuery(Request $request, bool $withStatus = true)
    {
        return Extracurricular::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->hari, fn($q, $h) => $q->where('hari', $h))
            ->when($withStatus && $request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($withStatus && $request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($withStatus && $request->status === 'no_pembina', fn($q) => $q->where('is_active', true)->whereNull('pembina_id'))
            ->when($withStatus && $request->status === 'idle', fn($q) => $q->where('is_active', true)
                ->whereNotExists(fn($e) => $e->from('extracurricular_attendances')->whereColumn('extracurricular_attendances.extracurricular_id', 'extracurriculars.id')
                    ->where('tanggal', '>=', today()->subDays(29)->toDateString())))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                // Cari nama ekskul / pembina, atau ekskul yang diikuti murid dengan nama/NIS tsb
                $q->where(fn($w) => $w->where('name', 'like', $like)
                    ->orWhereHas('pembina', fn($p) => $p->where('name', 'like', $like))
                    ->orWhereIn('id', ExtracurricularMember::whereIn(
                        'student_id',
                        Student::withTrashed()->where(fn($st) => $st->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id')
                    )->select('extracurricular_id')));
            });
    }

    private function achievementQuery(Request $request, bool $withLevel = true)
    {
        return StudentAchievement::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->extracurricular_id, fn($q, $id) => $q->where('extracurricular_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->whereIn('student_id', Student::withTrashed()->where('class_id', $id)->select('id')))
            ->when($withLevel && $request->level, fn($q) => $q->where('level', $request->level))
            ->when($request->year, fn($q, $y) => $q->whereYear('achieved_at', $y))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('title', 'like', $like)
                    ->orWhereIn('student_id', Student::withTrashed()->where(fn($st) => $st->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id')));
            });
    }

    public static function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url(ltrim($path, '/'));
    }

    private function deleteFile(?string $path): void
    {
        if (!$path || preg_match('#^https?://#i', $path)) {
            return;
        }

        try {
            Storage::disk('public')->delete(ltrim($path, '/'));
        } catch (\Throwable) {
        }
    }
}
