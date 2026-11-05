<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\HealthRecord;
use App\Models\Student;
use App\Models\UksVisit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kesehatan / UKS semua sekolah (health_records & uks_visits) — HANYA LIHAT.
 *
 * Data kesehatan murid termasuk data pribadi sensitif. Karena itu:
 *  - tidak ada edit / hapus dari superadmin (dikelola sekolah lewat aplikasi);
 *  - daftar hanya menampilkan ringkasan; detail kesehatan per murid dibuka per orang
 *    dan setiap pembukaan dicatat di Audit Log;
 *  - export hanya rekap angka per sekolah (tanpa nama & keluhan murid).
 */
class SuperAdminHealthController extends Controller
{
    public const TABS = [
        'recap'   => 'Rekap per Sekolah',
        'visits'  => 'Kunjungan UKS',
        'records' => 'Data Kesehatan',
    ];

    public const OUTCOMES = ['kembali_kelas' => 'Kembali ke kelas', 'pulang' => 'Dipulangkan', 'dirujuk' => 'Dirujuk'];
    public const OUTCOME_BADGE = ['kembali_kelas' => 'success', 'pulang' => 'warning', 'dirujuk' => 'danger'];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'recap';
        [$from, $to] = $this->range($request);

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'from'         => $from,
            'to'           => $to,
            'schools'      => $this->schools(),
            'companyNames' => Company::pluck('name', 'id'),
            'classOptions' => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->where('is_active', true)->orderBy('grade_level')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'outcomes'     => self::OUTCOMES,
            'outcomeBadge' => self::OUTCOME_BADGE,
        ];

        if ($tab === 'visits') {
            $query = $this->visitQuery($request, $from, $to);

            return view('pages.superadmin.school.health.index', $common + [
                'items'     => (clone $query)->with([
                    'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'deleted_at')->with('classRoom:id,name'),
                    'recordedBy:id,name',
                ])->latest('visited_at')->paginate(30)->withQueryString(),
                'byOutcome' => $this->visitQuery($request, $from, $to, withOutcome: false)->toBase()
                    ->selectRaw('outcome, count(*) as total')->groupBy('outcome')->pluck('total', 'outcome'),
                'frequent'  => $this->visitQuery($request, $from, $to, withOutcome: false)->toBase()
                    ->selectRaw('student_id, count(*) as total')->groupBy('student_id')->havingRaw('count(*) >= 3')
                    ->orderByDesc('total')->limit(10)->get(),
                'frequentNames' => Student::withTrashed()->whereIn(
                    'id',
                    $this->visitQuery($request, $from, $to, withOutcome: false)->toBase()->select('student_id')->groupBy('student_id')->havingRaw('count(*) >= 3')
                )->pluck('name', 'id'),
            ]);
        }

        if ($tab === 'records') {
            $query = $this->recordQuery($request);

            return view('pages.superadmin.school.health.index', $common + [
                'items'   => (clone $query)->with([
                    'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'deleted_at')->with('classRoom:id,name'),
                ])->latest('recorded_at')->paginate(30)->withQueryString(),
                'summary' => [
                    'total'        => (clone $query)->count(),
                    'allergy'      => (clone $query)->whereNotNull('allergies')->where('allergies', '<>', '')->count(),
                    'chronic'      => (clone $query)->whereNotNull('chronic_conditions')->where('chronic_conditions', '<>', '')->count(),
                    'no_emergency' => (clone $query)->where(fn($q) => $q->whereNull('emergency_contact_phone')->orWhere('emergency_contact_phone', ''))->count(),
                ],
            ]);
        }

        return view('pages.superadmin.school.health.index', $common + ['recap' => $this->recap($request, $from, $to)]);
    }

    // =========================================================
    // DETAIL KESEHATAN 1 MURID (dicatat di Audit Log)
    // =========================================================
    public function show(Request $request, $studentId)
    {
        $student = Student::withTrashed()
            ->with(['company:id,name', 'classRoom:id,name,academic_year', 'healthRecord.recordedBy:id,name', 'guardians:id,name,phone'])
            ->findOrFail($studentId);

        $visits = UksVisit::where('student_id', $student->id)
            ->with('recordedBy:id,name')
            ->latest('visited_at')
            ->get();

        // Akses data kesehatan pribadi dicatat
        AuditLog::record('view_student_health', null, "Membuka data kesehatan murid {$student->name} (tenant #{$student->company_id})", [
            'student_id' => $student->id,
            'company_id' => $student->company_id,
        ]);

        $record = $student->healthRecord;
        $bmi = null;
        if ($record && $record->height_cm > 0 && $record->weight_kg > 0) {
            $m = (float) $record->height_cm / 100;
            $bmi = round((float) $record->weight_kg / ($m * $m), 1);
        }

        return view('pages.superadmin.school.health.show', [
            'student'      => $student,
            'record'       => $record,
            'bmi'          => $bmi,
            'visits'       => $visits,
            'outcomes'     => self::OUTCOMES,
            'outcomeBadge' => self::OUTCOME_BADGE,
        ]);
    }

    // =========================================================
    // EXPORT — hanya rekap angka per sekolah
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $recap = $this->recap($request, $from, $to);

        AuditLog::record('export_health_recap', null, 'Export rekap UKS per sekolah (tanpa data pribadi)', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($recap) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sekolah', 'Murid aktif', 'Punya data kesehatan', '% data kesehatan', 'Punya alergi', 'Penyakit kronis', 'Kunjungan UKS', 'Murid berkunjung', 'Kembali ke kelas', 'Dipulangkan', 'Dirujuk', 'Kunjungan terakhir'], ';');
            foreach ($recap as $r) {
                fputcsv($out, [$r['name'], $r['students'], $r['records'], $r['coverage'], $r['allergy'], $r['chronic'], $r['visits'], $r['visitors'], $r['kembali_kelas'], $r['pulang'], $r['dirujuk'], $r['last_visit']], ';');
            }
            fclose($out);
        }, 'rekap-uks-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY & HELPER
    // =========================================================
    private function recap(Request $request, $from, $to)
    {
        $schools = $this->schools()->when($request->company_id, fn($c) => $c->where('id', (int) $request->company_id));
        $ids = $schools->pluck('id')->all();

        $students = Student::whereIn('company_id', $ids)->where('is_active', true)
            ->selectRaw('company_id, count(*) as total')->groupBy('company_id')->pluck('total', 'company_id');

        $records = HealthRecord::whereIn('company_id', $ids)
            ->whereIn('student_id', Student::where('is_active', true)->select('id'))
            ->toBase()
            ->selectRaw("company_id, count(*) as total,
                sum(allergies is not null and allergies <> '') as allergy,
                sum(chronic_conditions is not null and chronic_conditions <> '') as chronic")
            ->groupBy('company_id')->get()->keyBy('company_id');

        $visits = UksVisit::whereIn('company_id', $ids)
            ->whereBetween('visited_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->toBase()
            ->selectRaw("company_id, count(*) as total, count(distinct student_id) as visitors,
                sum(outcome = 'kembali_kelas') as kembali_kelas, sum(outcome = 'pulang') as pulang, sum(outcome = 'dirujuk') as dirujuk,
                max(visited_at) as last_visit")
            ->groupBy('company_id')->get()->keyBy('company_id');

        return $schools->map(function ($s) use ($students, $records, $visits) {
            $st = (int) ($students[$s->id] ?? 0);
            $r = $records[$s->id] ?? null;
            $v = $visits[$s->id] ?? null;

            return [
                'id'            => $s->id,
                'name'          => $s->name,
                'students'      => $st,
                'records'       => (int) ($r->total ?? 0),
                'coverage'      => $st ? round(((int) ($r->total ?? 0)) / $st * 100) : null,
                'allergy'       => (int) ($r->allergy ?? 0),
                'chronic'       => (int) ($r->chronic ?? 0),
                'visits'        => (int) ($v->total ?? 0),
                'visitors'      => (int) ($v->visitors ?? 0),
                'kembali_kelas' => (int) ($v->kembali_kelas ?? 0),
                'pulang'        => (int) ($v->pulang ?? 0),
                'dirujuk'       => (int) ($v->dirujuk ?? 0),
                'last_visit'    => $v->last_visit ?? null,
            ];
        })->values();
    }

    private function visitQuery(Request $request, $from, $to, bool $withOutcome = true)
    {
        return UksVisit::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->whereBetween('visited_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->whereIn('student_id', Student::withTrashed()->where('class_id', $id)->select('id')))
            ->when($withOutcome && $request->outcome, fn($q) => $q->where('outcome', $request->outcome))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->whereIn('student_id', Student::withTrashed()->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id'));
            });
    }

    private function recordQuery(Request $request)
    {
        return HealthRecord::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->whereIn('student_id', Student::withTrashed()->where('class_id', $id)->select('id')))
            ->when($request->flag === 'allergy', fn($q) => $q->whereNotNull('allergies')->where('allergies', '<>', ''))
            ->when($request->flag === 'chronic', fn($q) => $q->whereNotNull('chronic_conditions')->where('chronic_conditions', '<>', ''))
            ->when($request->flag === 'no_emergency', fn($q) => $q->where(fn($w) => $w->whereNull('emergency_contact_phone')->orWhere('emergency_contact_phone', '')))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->whereIn('student_id', Student::withTrashed()->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like))->select('id'));
            });
    }

    /** Default 30 hari terakhir */
    private function range(Request $request): array
    {
        try {
            $to = $request->date_to ? \Carbon\Carbon::parse($request->date_to)->startOfDay() : today();
            $from = $request->date_from ? \Carbon\Carbon::parse($request->date_from)->startOfDay() : $to->copy()->subDays(29);
        } catch (\Throwable) {
            $to = today();
            $from = $to->copy()->subDays(29);
        }

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function schools()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name']);
    }
}
