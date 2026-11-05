<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PpdbApplicant;
use App\Models\PpdbPeriod;
use App\Models\Student;
use App\Models\StudentMutation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring PPDB (penerimaan murid baru) & Mutasi Murid (pindah masuk/keluar) semua sekolah.
 * Tabel: ppdb_periods, ppdb_applicants, ppdb_test_scores, student_mutations.
 *
 * Dikelola Admin Sekolah. Superadmin: pantau, export, dan hapus data yang salah/ganda
 * (wajib alasan, dokumen ikut terhapus, tercatat di Audit Log).
 */
class SuperAdminPpdbController extends Controller
{
    public const TABS = [
        'periods'    => 'Periode PPDB',
        'applicants' => 'Pendaftar',
        'mutations'  => 'Mutasi Murid',
    ];

    public const APPLICANT_STATUS = [
        'pending'      => 'Menunggu',
        'tes'          => 'Sedang Tes',
        'lulus'        => 'Lulus',
        'tidak_lulus'  => 'Tidak Lulus',
        'daftar_ulang' => 'Daftar Ulang',
        'aktif'        => 'Jadi Murid',
    ];
    public const APPLICANT_BADGE = [
        'pending'      => 'secondary',
        'tes'          => 'info',
        'lulus'        => 'success',
        'tidak_lulus'  => 'danger',
        'daftar_ulang' => 'warning',
        'aktif'        => 'primary',
    ];

    public const TEST_TYPES = ['akademik' => 'Akademik', 'baca_quran' => "Baca Al-Qur'an", 'hafalan' => 'Hafalan', 'wawancara' => 'Wawancara'];

    public const MUTATION_TYPES = ['masuk' => 'Pindah Masuk', 'keluar' => 'Pindah Keluar'];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'periods';

        $common = [
            'tab'             => $tab,
            'tabs'            => self::TABS,
            'schools'         => Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name']),
            'companyNames'    => Company::pluck('name', 'id'),
            'periodOptions'   => $request->company_id
                ? PpdbPeriod::where('company_id', $request->company_id)->latest('registration_start')->get(['id', 'name', 'academic_year'])
                : collect(),
            'statusLabels'    => self::APPLICANT_STATUS,
            'statusBadge'     => self::APPLICANT_BADGE,
            'mutationTypes'   => self::MUTATION_TYPES,
        ];

        if ($tab === 'applicants') {
            $query = $this->applicantQuery($request);

            return view('pages.superadmin.school.ppdb.index', $common + [
                'items'    => (clone $query)->with(['period:id,name', 'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'deleted_at')])
                    ->withCount('testScores')->withAvg('testScores as avg_score', 'score')
                    ->latest()->paginate(30)->withQueryString(),
                'byStatus' => $this->applicantQuery($request, withStatus: false)->toBase()
                    ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            ]);
        }

        if ($tab === 'mutations') {
            $query = $this->mutationQuery($request);

            return view('pages.superadmin.school.ppdb.index', $common + [
                'items'    => (clone $query)->with([
                    'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'is_active', 'deleted_at'),
                    'processedBy:id,name',
                ])->latest('effective_date')->latest('id')->paginate(30)->withQueryString(),
                'byType'   => $this->mutationQuery($request, withType: false)->toBase()
                    ->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            ]);
        }

        // Periode + rekap status pendaftar per periode
        $periods = PpdbPeriod::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->status === 'open', fn($q) => $q->where('is_active', true)->whereDate('registration_start', '<=', today())->whereDate('registration_end', '>=', today()))
            ->when($request->status === 'upcoming', fn($q) => $q->whereDate('registration_start', '>', today()))
            ->when($request->status === 'closed', fn($q) => $q->whereDate('registration_end', '<', today()))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'))
            ->withCount([
                'applicants',
                'applicants as accepted_count' => fn($q) => $q->whereIn('status', ['lulus', 'daftar_ulang', 'aktif']),
                'applicants as enrolled_count' => fn($q) => $q->where('status', 'aktif'),
                'applicants as waiting_count'  => fn($q) => $q->whereIn('status', ['pending', 'tes']),
            ])
            ->orderByDesc('registration_start')
            ->paginate(30)->withQueryString();

        // Lebih dari 1 periode aktif dalam 1 sekolah (aplikasi seharusnya hanya 1)
        $multiActive = PpdbPeriod::where('is_active', true)->groupBy('company_id')->havingRaw('count(*) > 1')->pluck('company_id');

        return view('pages.superadmin.school.ppdb.index', $common + ['items' => $periods, 'multiActive' => $multiActive]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show(string $tab, $id)
    {
        if ($tab === 'periods') {
            $period = PpdbPeriod::with('company:id,name')->findOrFail($id);

            return view('pages.superadmin.school.ppdb.show-period', [
                'period'       => $period,
                'applicants'   => PpdbApplicant::where('ppdb_period_id', $period->id)
                    ->withAvg('testScores as avg_score', 'score')->orderBy('full_name')->get(),
                'byStatus'     => PpdbApplicant::where('ppdb_period_id', $period->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'byGender'     => PpdbApplicant::where('ppdb_period_id', $period->id)->selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total', 'gender'),
                'boarding'     => PpdbApplicant::where('ppdb_period_id', $period->id)->where('wants_boarding', true)->count(),
                'statusLabels' => self::APPLICANT_STATUS,
                'statusBadge'  => self::APPLICANT_BADGE,
            ]);
        }

        if ($tab === 'applicants') {
            $applicant = PpdbApplicant::with([
                'company:id,name',
                'period',
                'testScores' => fn($q) => $q->with('testedBy:id,name'),
                'student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'),
            ])->findOrFail($id);

            return view('pages.superadmin.school.ppdb.show-applicant', [
                'applicant'    => $applicant,
                'statusLabels' => self::APPLICANT_STATUS,
                'statusBadge'  => self::APPLICANT_BADGE,
                'testTypes'    => self::TEST_TYPES,
            ]);
        }

        abort(404);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);
        $reason = $data['reason'];

        if ($tab === 'periods') {
            $period = PpdbPeriod::withCount('applicants')->findOrFail($id);
            if ($period->applicants_count > 0) {
                return back()->with('error', "Periode {$period->name} sudah punya {$period->applicants_count} pendaftar. Menghapusnya akan ikut menghapus semua pendaftar & nilai tesnya.");
            }

            $snapshot = $period->getAttributes();
            $period->delete();

            AuditLog::record('delete_ppdb_period', null, "Periode PPDB {$period->name} (tenant #{$period->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

            return redirect()->route('superadmin.school.ppdb.index', ['tab' => 'periods', 'company_id' => $period->company_id])->with('success', "Periode {$period->name} dihapus.");
        }

        if ($tab === 'applicants') {
            $applicant = PpdbApplicant::with('testScores')->findOrFail($id);
            $snapshot = $applicant->getAttributes() + ['test_scores' => $applicant->testScores->map->getAttributes()->all()];

            DB::transaction(fn() => $applicant->delete()); // nilai tes ikut terhapus (cascade)

            AuditLog::record('delete_ppdb_applicant', null, "Pendaftar PPDB {$applicant->full_name} (tenant #{$applicant->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

            $msg = "Pendaftar {$applicant->full_name} dihapus.";
            if ($applicant->student_id) {
                $msg .= ' Data murid hasil konversinya tetap ada.';
            }

            return redirect()->route('superadmin.school.ppdb.index', ['tab' => 'applicants', 'company_id' => $applicant->company_id])->with('success', $msg);
        }

        abort_unless($tab === 'mutations', 404);

        $mutation = StudentMutation::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
        $snapshot = $mutation->getAttributes();
        $doc = $mutation->document_path;

        $mutation->delete();
        if ($doc) {
            try {
                Storage::disk('public')->delete($doc);
            } catch (\Throwable) {
            }
        }

        AuditLog::record('delete_student_mutation', null, "Mutasi {$mutation->type} murid {$mutation->student?->name} (tenant #{$mutation->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

        $msg = 'Catatan mutasi dihapus.';
        if ($mutation->type === 'keluar') {
            $msg .= ' Murid TIDAK otomatis diaktifkan kembali — atur di aplikasi sekolah bila perlu.';
        }

        return redirect()->route('superadmin.school.ppdb.index', ['tab' => 'mutations', 'company_id' => $mutation->company_id])->with('success', $msg);
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'periods';
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_ppdb_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($tab === 'applicants') {
                fputcsv($out, ['Sekolah', 'Periode', 'Nama', 'L/P', 'Tanggal lahir', 'Asal sekolah', 'Orang tua', 'No. HP', 'Email', 'Asrama', 'Rata-rata tes', 'Status', 'Catatan', 'Daftar'], ';');
                $this->applicantQuery($request)->with('period:id,name')->withAvg('testScores as avg_score', 'score')->orderBy('id')
                    ->chunk(500, function ($rows) use ($out, $companyNames) {
                        foreach ($rows as $a) {
                            fputcsv($out, [
                                $companyNames[$a->company_id] ?? '-',
                                $a->period->name ?? '',
                                $a->full_name,
                                $a->gender,
                                optional($a->birth_date)->format('Y-m-d'),
                                $a->previous_school,
                                $a->parent_name,
                                $a->parent_phone,
                                $a->parent_email,
                                $a->wants_boarding ? 'Ya' : 'Tidak',
                                $a->avg_score !== null ? round((float) $a->avg_score, 2) : '',
                                self::APPLICANT_STATUS[$a->status] ?? $a->status,
                                $a->notes,
                                optional($a->created_at)->format('Y-m-d'),
                            ], ';');
                        }
                    });
            } elseif ($tab === 'mutations') {
                fputcsv($out, ['Sekolah', 'Jenis', 'Murid', 'NIS', 'Tanggal efektif', 'Asal / tujuan', 'No. surat', 'Alasan', 'Dokumen', 'Diproses oleh'], ';');
                $this->mutationQuery($request)->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis'), 'processedBy:id,name'])->orderBy('id')
                    ->chunk(500, function ($rows) use ($out, $companyNames) {
                        foreach ($rows as $m) {
                            fputcsv($out, [
                                $companyNames[$m->company_id] ?? '-',
                                self::MUTATION_TYPES[$m->type] ?? $m->type,
                                $m->student->name ?? '',
                                $m->student->nis ?? '',
                                optional($m->effective_date)->format('Y-m-d'),
                                $m->type === 'masuk' ? $m->origin_school : $m->destination_school,
                                $m->letter_number,
                                $m->reason,
                                $m->document_path ? 'Ada' : '',
                                $m->processedBy->name ?? '',
                            ], ';');
                        }
                    });
            } else {
                fputcsv($out, ['Sekolah', 'Periode', 'Tahun ajaran', 'Buka', 'Tutup', 'Kuota', 'Pendaftar', 'Diterima', 'Jadi murid', 'Aktif'], ';');
                PpdbPeriod::whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->withCount(['applicants', 'applicants as accepted_count' => fn($q) => $q->whereIn('status', ['lulus', 'daftar_ulang', 'aktif']), 'applicants as enrolled_count' => fn($q) => $q->where('status', 'aktif')])
                    ->orderBy('company_id')->orderBy('registration_start')
                    ->each(function ($p) use ($out, $companyNames) {
                        fputcsv($out, [
                            $companyNames[$p->company_id] ?? '-',
                            $p->name,
                            $p->academic_year,
                            optional($p->registration_start)->format('Y-m-d'),
                            optional($p->registration_end)->format('Y-m-d'),
                            $p->quota ?? 'Tanpa batas',
                            $p->applicants_count,
                            $p->accepted_count,
                            $p->enrolled_count,
                            $p->is_active ? 'Ya' : 'Tidak',
                        ], ';');
                    });
            }

            fclose($out);
        }, 'ppdb-' . $tab . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY
    // =========================================================
    private function applicantQuery(Request $request, bool $withStatus = true)
    {
        return PpdbApplicant::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->period_id, fn($q, $id) => $q->where('ppdb_period_id', $id))
            ->when($request->boarding === '1', fn($q) => $q->where('wants_boarding', true))
            ->when($withStatus && $request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('full_name', 'like', $like)->orWhere('parent_name', 'like', $like)->orWhere('parent_phone', 'like', $like));
            });
    }

    private function mutationQuery(Request $request, bool $withType = true)
    {
        return StudentMutation::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($withType && $request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn($q, $d) => $q->whereDate('effective_date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->whereDate('effective_date', '<=', $d))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('origin_school', 'like', $like)->orWhere('destination_school', 'like', $like)->orWhere('letter_number', 'like', $like)
                    ->orWhereIn('student_id', Student::withTrashed()->where(fn($st) => $st->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id')));
            });
    }
}
