<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\ClassPromotion;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\LearningMaterial;
use App\Models\LessonSchedule;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Akademik semua sekolah: Mapel, Kurikulum, Jadwal Pelajaran, Kalender Akademik,
 * Nilai, Kenaikan Kelas, Materi, dan Tugas.
 *
 * Semua data dikelola Admin Sekolah & Guru lewat aplikasi. Superadmin: pantau, cari masalah
 * (jadwal bentrok, mapel tidak terpakai, tugas belum dikoreksi), export, dan hapus data yang salah
 * (wajib alasan, file ikut terhapus, tercatat di Audit Log).
 */
class SuperAdminAcademicController extends Controller
{
    public const TABS = [
        'subjects'    => 'Mapel',
        'curricula'   => 'Kurikulum',
        'schedules'   => 'Jadwal Pelajaran',
        'events'      => 'Kalender',
        'grades'      => 'Nilai',
        'promotions'  => 'Kenaikan Kelas',
        'materials'   => 'Materi',
        'assignments' => 'Tugas',
    ];

    public const DAYS = ['senin' => 'Senin', 'selasa' => 'Selasa', 'rabu' => 'Rabu', 'kamis' => 'Kamis', 'jumat' => 'Jumat', 'sabtu' => 'Sabtu'];

    public const EVENT_TYPES = ['libur' => 'Libur', 'ujian' => 'Ujian', 'kegiatan' => 'Kegiatan', 'penting' => 'Penting'];
    public const EVENT_BADGE = ['libur' => 'danger', 'ujian' => 'warning', 'kegiatan' => 'info', 'penting' => 'primary'];

    public const GRADE_TYPES = ['tugas' => 'Tugas', 'ujian' => 'Ujian'];
    public const SEMESTERS = ['ganjil' => 'Ganjil', 'genap' => 'Genap'];

    public const SUBJECT_GROUPS = ['wajib' => 'Wajib', 'muatan_lokal' => 'Muatan Lokal'];

    public const PROMOTION_STATUS = ['naik' => 'Naik', 'tinggal' => 'Tinggal Kelas', 'lulus' => 'Lulus'];
    public const PROMOTION_BADGE = ['naik' => 'success', 'tinggal' => 'warning', 'lulus' => 'primary'];

    public const SUBMISSION_STATUS = ['belum_dikoreksi' => 'Belum dikoreksi', 'dikoreksi' => 'Sudah dikoreksi'];

    /** Tabel yang ikut terhapus (cascade) kalau mapel dihapus */
    private const SUBJECT_USAGE = [
        'curriculum_subjects' => 'kurikulum',
        'lesson_schedules'    => 'jadwal',
        'grades'              => 'nilai',
        'learning_materials'  => 'materi',
        'assignments'         => 'tugas',
    ];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'subjects';

        $data = [
            'tab'           => $tab,
            'tabs'          => self::TABS,
            'schools'       => $this->schools(),
            'companyNames'  => Company::pluck('name', 'id'),
            'academicYears' => ClassRoom::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'classOptions'  => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->orderByDesc('is_active')->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'academic_year', 'is_active'])
                : collect(),
            'subjectOptions' => $request->company_id
                ? Subject::where('company_id', $request->company_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
            'counts'        => $this->tabCounts($request),
        ];

        $query = $this->query($tab, $request);

        $data['items'] = match ($tab) {
            'subjects' => $query->orderBy('company_id')->orderByDesc('is_active')->orderBy('name')->paginate(30),
            'curricula' => $query->orderBy('company_id')->orderByDesc('academic_year')->orderByDesc('is_active')->paginate(30),
            'schedules' => $query->orderBy('lesson_schedules.company_id')->orderBy('lesson_schedules.class_id')
                ->orderByRaw("FIELD(lesson_schedules.hari, 'senin','selasa','rabu','kamis','jumat','sabtu')")
                ->orderBy('lesson_schedules.jam_mulai')->paginate(50),
            'events' => $query->orderByDesc('tanggal_mulai')->paginate(30),
            'grades' => $query->orderByDesc('tanggal')->orderByDesc('id')->paginate(30),
            'promotions' => $query->latest()->paginate(30),
            'materials' => $query->latest()->paginate(30),
            'assignments' => $query->orderByDesc('deadline')->paginate(30),
        };
        $data['items']->withQueryString();

        // Ringkasan khusus per tab
        if ($tab === 'grades') {
            $data['gradeSummary'] = $this->query('grades', $request)->toBase()
                ->selectRaw('count(*) as total, avg(nilai) as avg, min(nilai) as min, max(nilai) as max, sum(nilai < 75) as below')
                ->first();
        }
        if ($tab === 'schedules') {
            $data['conflictCount'] = $this->query('schedules', $request)->get()
                ->filter(fn($s) => $s->class_conflicts || $s->teacher_conflicts)->count();
        }
        if ($tab === 'assignments') {
            $data['ungraded'] = AssignmentSubmission::whereIn('assignment_id', $this->query('assignments', $request)->select('assignments.id'))
                ->where('status', 'belum_dikoreksi')->count();
        }

        return view('pages.superadmin.school.academics.index', $data + [
            'days'             => self::DAYS,
            'eventTypes'       => self::EVENT_TYPES,
            'eventBadge'       => self::EVENT_BADGE,
            'gradeTypes'       => self::GRADE_TYPES,
            'semesters'        => self::SEMESTERS,
            'subjectGroups'    => self::SUBJECT_GROUPS,
            'promotionStatus'  => self::PROMOTION_STATUS,
            'promotionBadge'   => self::PROMOTION_BADGE,
            'subjectUsage'     => self::SUBJECT_USAGE,
        ]);
    }

    // =========================================================
    // SHOW (kurikulum & tugas)
    // =========================================================
    public function show(string $tab, $id)
    {
        if ($tab === 'curricula') {
            $item = Curriculum::with(['company:id,name', 'curriculumSubjects' => fn($q) => $q->with('subject:id,name,code,kelompok,is_active')->orderBy('grade_level')])
                ->findOrFail($id);

            return view('pages.superadmin.school.academics.show-curriculum', [
                'item'          => $item,
                'byGrade'       => $item->curriculumSubjects->groupBy('grade_level')->sortKeys(),
                'subjectGroups' => self::SUBJECT_GROUPS,
                'siblings'      => Curriculum::where('company_id', $item->company_id)->where('academic_year', $item->academic_year)
                    ->where('id', '!=', $item->id)->get(['id', 'name', 'is_active']),
            ]);
        }

        abort_unless($tab === 'assignments', 404);

        $item = Assignment::with(['company:id,name', 'classRoom:id,name,academic_year', 'subject:id,name', 'teacher:id,name,email'])
            ->findOrFail($id);

        $submissions = AssignmentSubmission::where('assignment_id', $item->id)
            ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'deleted_at'), 'submittedBy:id,name', 'gradedBy:id,name'])
            ->orderBy('submitted_at')
            ->get();

        // Murid kelas yang belum mengumpulkan
        $missing = Student::where('class_id', $item->class_id)->where('is_active', true)
            ->whereNotIn('id', $submissions->pluck('student_id'))
            ->orderBy('name')->get(['id', 'name', 'nis']);

        return view('pages.superadmin.school.academics.show-assignment', [
            'item'         => $item,
            'fileUrl'      => self::fileUrl($item->file_path),
            'submissions'  => $submissions,
            'missing'      => $missing,
            'statusLabels' => self::SUBMISSION_STATUS,
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        abort_unless(array_key_exists($tab, self::TABS), 404);

        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);
        $reason = $data['reason'];

        $model = match ($tab) {
            'subjects'    => Subject::findOrFail($id),
            'curricula'   => Curriculum::with('curriculumSubjects')->findOrFail($id),
            'schedules'   => LessonSchedule::with(['classRoom:id,name', 'subject:id,name'])->findOrFail($id),
            'events'      => AcademicEvent::findOrFail($id),
            'grades'      => Grade::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name'), 'subject:id,name'])->findOrFail($id),
            'promotions'  => ClassPromotion::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id),
            'materials'   => LearningMaterial::findOrFail($id),
            'assignments' => Assignment::with('submissions')->findOrFail($id),
        };

        // Mapel yang masih dipakai tidak boleh dihapus: kurikulum, jadwal, nilai, materi & tugas ikut terhapus (cascade)
        if ($tab === 'subjects') {
            $used = array_filter($this->subjectUsage($model->id));
            if ($used) {
                $list = collect($used)->map(fn($n, $t) => $n . ' ' . self::SUBJECT_USAGE[$t])->implode(', ');

                return back()->with('error', "Mapel {$model->name} masih dipakai ({$list}). Menghapusnya akan ikut menghapus data tersebut. Nonaktifkan saja dari aplikasi sekolah.");
            }
        }

        $snapshot = $model->getAttributes();
        $files = [];

        if ($tab === 'curricula') {
            $snapshot['curriculum_subjects'] = $model->curriculumSubjects->map->getAttributes()->all();
        }
        if ($tab === 'materials') {
            $files[] = $model->file_path;
        }
        if ($tab === 'assignments') {
            $files[] = $model->file_path;
            foreach ($model->submissions as $s) {
                $files[] = $s->file_path;
            }
            $snapshot['submissions'] = $model->submissions->map(fn($s) => collect($s->getAttributes())->only(['id', 'student_id', 'status', 'nilai', 'submitted_at']))->all();
        }

        DB::transaction(fn() => $model->delete());

        foreach (array_filter($files) as $file) {
            $this->deleteFile($file);
        }

        $label = $this->describe($tab, $model);

        AuditLog::record('delete_academic_' . $tab, null, "{$label} (tenant #{$model->company_id}) dihapus. Alasan: {$reason}", $snapshot + ['reason' => $reason]);

        $message = "{$label} dihapus.";
        if ($tab === 'promotions') {
            $message .= ' Kelas murid tidak berubah — atur ulang kelasnya di menu Murid bila perlu.';
        }

        return redirect()->route('superadmin.school.academics.index', ['tab' => $tab, 'company_id' => $model->company_id])
            ->with('success', $message);
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'subjects';
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_academic_' . $tab, null, 'Export ' . self::TABS[$tab] . ' ke CSV', ['filters' => $request->query()]);

        [$header, $mapper] = $this->exportColumns($tab, $companyNames);

        return response()->streamDownload(function () use ($tab, $request, $header, $mapper) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');

            $orderCol = match ($tab) {
                'schedules' => 'lesson_schedules.id',
                'assignments' => 'assignments.id',
                default => 'id',
            };

            $this->query($tab, $request)->orderBy($orderCol)->chunk(500, function ($rows) use ($out, $mapper) {
                foreach ($rows as $row) {
                    fputcsv($out, $mapper($row), ';');
                }
            });

            fclose($out);
        }, 'akademik-' . $tab . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY PER TAB
    // =========================================================
    private function query(string $tab, Request $request)
    {
        $schoolIds = Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id');
        $like = $request->search ? '%' . addcslashes($request->search, '%_\\') . '%' : null;
        $studentIds = fn() => Student::withTrashed()->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like)->orWhere('nisn', 'like', $like))->select('id');

        switch ($tab) {
            case 'subjects':
                $q = Subject::query()->whereIn('company_id', $schoolIds);
                foreach (self::SUBJECT_USAGE as $table => $label) {
                    $q->selectSub(DB::table($table)->whereColumn("{$table}.subject_id", 'subjects.id')->selectRaw('count(*)'), $table . '_count');
                }

                return $q->addSelect('subjects.*')
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->when($request->kelompok, fn($q, $k) => $q->where('kelompok', $k))
                    ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
                    ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
                    ->when($request->status === 'unused', function ($q) {
                        foreach (array_keys(self::SUBJECT_USAGE) as $table) {
                            $q->whereNotExists(fn($e) => $e->from($table)->whereColumn("{$table}.subject_id", 'subjects.id'));
                        }
                    })
                    ->when($like, fn($q) => $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)));

            case 'curricula':
                return Curriculum::query()->whereIn('company_id', $schoolIds)
                    ->withCount('curriculumSubjects')
                    ->withSum('curriculumSubjects as total_hours', 'jam_per_minggu')
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->when($request->academic_year, fn($q, $y) => $q->where('academic_year', $y))
                    ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
                    ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
                    ->when($like, fn($q) => $q->where('name', 'like', $like));

            case 'schedules':
                $overlap = fn($col) => fn($q) => $q->from('lesson_schedules as o')
                    ->whereColumn("o.{$col}", "lesson_schedules.{$col}")
                    ->whereColumn('o.hari', 'lesson_schedules.hari')
                    ->whereColumn('o.academic_year', 'lesson_schedules.academic_year')
                    ->whereColumn('o.id', '!=', 'lesson_schedules.id')
                    ->whereColumn('o.jam_mulai', '<', 'lesson_schedules.jam_selesai')
                    ->whereColumn('o.jam_selesai', '>', 'lesson_schedules.jam_mulai')
                    ->selectRaw('count(*)');

                return LessonSchedule::query()
                    ->select('lesson_schedules.*')
                    ->selectSub($overlap('class_id'), 'class_conflicts')
                    ->selectSub($overlap('teacher_id'), 'teacher_conflicts')
                    ->with(['classRoom:id,name', 'subject:id,name', 'teacher:id,name,role,company_id,is_active'])
                    ->whereIn('lesson_schedules.company_id', $schoolIds)
                    ->when($request->company_id, fn($q, $id) => $q->where('lesson_schedules.company_id', $id))
                    ->when($request->academic_year, fn($q, $y) => $q->where('lesson_schedules.academic_year', $y))
                    ->when($request->class_id, fn($q, $id) => $q->where('lesson_schedules.class_id', $id))
                    ->when($request->subject_id, fn($q, $id) => $q->where('lesson_schedules.subject_id', $id))
                    ->when($request->hari, fn($q, $h) => $q->where('lesson_schedules.hari', $h))
                    ->when($request->status === 'conflict', fn($q) => $q->where(fn($w) => $w->whereExists($overlap('class_id'))->orWhereExists($overlap('teacher_id'))))
                    ->when($like, fn($q) => $q->whereHas('teacher', fn($t) => $t->where('name', 'like', $like)));

            case 'events':
                return AcademicEvent::query()->whereIn('company_id', $schoolIds)
                    ->with(['classRoom:id,name', 'createdBy:id,name'])
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->when($request->academic_year, fn($q, $y) => $q->where('academic_year', $y))
                    ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
                    ->when($request->jenis, fn($q, $j) => $q->where('jenis', $j))
                    ->when($request->status === 'upcoming', fn($q) => $q->whereDate('tanggal_selesai', '>=', today()))
                    ->when($request->status === 'past', fn($q) => $q->whereDate('tanggal_selesai', '<', today()))
                    ->when($like, fn($q) => $q->where('title', 'like', $like));

            case 'grades':
                return Grade::query()->whereIn('company_id', $schoolIds)
                    ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'deleted_at'), 'classRoom:id,name', 'subject:id,name', 'teacher:id,name'])
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->when($request->academic_year, fn($q, $y) => $q->where('academic_year', $y))
                    ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
                    ->when($request->subject_id, fn($q, $id) => $q->where('subject_id', $id))
                    ->when($request->semester, fn($q, $s) => $q->where('semester', $s))
                    ->when($request->jenis, fn($q, $j) => $q->where('jenis', $j))
                    ->when($like, fn($q) => $q->where(fn($w) => $w->whereIn('student_id', $studentIds())->orWhere('judul', 'like', $like)));

            case 'promotions':
                return ClassPromotion::query()->whereIn('company_id', $schoolIds)
                    ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'deleted_at'), 'fromClass:id,name', 'toClass:id,name', 'processedBy:id,name'])
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->when($request->academic_year, fn($q, $y) => $q->where(fn($w) => $w->where('to_academic_year', $y)->orWhere('from_academic_year', $y)))
                    ->when($request->class_id, fn($q, $id) => $q->where(fn($w) => $w->where('from_class_id', $id)->orWhere('to_class_id', $id)))
                    ->when($request->status, fn($q, $s) => $q->where('status', $s))
                    ->when($like, fn($q) => $q->whereIn('student_id', $studentIds()));

            case 'materials':
                return LearningMaterial::query()->whereIn('company_id', $schoolIds)
                    ->with(['classRoom:id,name', 'subject:id,name', 'teacher:id,name'])
                    ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
                    ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
                    ->when($request->subject_id, fn($q, $id) => $q->where('subject_id', $id))
                    ->when($like, fn($q) => $q->where(fn($w) => $w->where('title', 'like', $like)->orWhereHas('teacher', fn($t) => $t->where('name', 'like', $like))));

            case 'assignments':
                return Assignment::query()->whereIn('assignments.company_id', $schoolIds)
                    ->with(['classRoom:id,name', 'subject:id,name', 'teacher:id,name'])
                    ->withCount([
                        'submissions',
                        'submissions as ungraded_count' => fn($q) => $q->where('status', 'belum_dikoreksi'),
                    ])
                    ->addSelect(['class_size' => Student::selectRaw('count(*)')->whereColumn('students.class_id', 'assignments.class_id')->where('is_active', true)])
                    ->when($request->company_id, fn($q, $id) => $q->where('assignments.company_id', $id))
                    ->when($request->class_id, fn($q, $id) => $q->where('assignments.class_id', $id))
                    ->when($request->subject_id, fn($q, $id) => $q->where('assignments.subject_id', $id))
                    ->when($request->status === 'open', fn($q) => $q->where('deadline', '>=', now()))
                    ->when($request->status === 'closed', fn($q) => $q->where('deadline', '<', now()))
                    ->when($request->status === 'ungraded', fn($q) => $q->whereHas('submissions', fn($s) => $s->where('status', 'belum_dikoreksi')))
                    ->when($like, fn($q) => $q->where(fn($w) => $w->where('title', 'like', $like)->orWhereHas('teacher', fn($t) => $t->where('name', 'like', $like))));
        }

        abort(404);
    }

    /** Jumlah data per tab (untuk badge tab), hanya filter sekolah */
    private function tabCounts(Request $request): array
    {
        $schoolIds = $request->company_id
            ? [(int) $request->company_id]
            : Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->pluck('id')->all();

        return [
            'subjects'    => Subject::whereIn('company_id', $schoolIds)->count(),
            'curricula'   => Curriculum::whereIn('company_id', $schoolIds)->count(),
            'schedules'   => LessonSchedule::whereIn('company_id', $schoolIds)->count(),
            'events'      => AcademicEvent::whereIn('company_id', $schoolIds)->count(),
            'grades'      => Grade::whereIn('company_id', $schoolIds)->count(),
            'promotions'  => ClassPromotion::whereIn('company_id', $schoolIds)->count(),
            'materials'   => LearningMaterial::whereIn('company_id', $schoolIds)->count(),
            'assignments' => Assignment::whereIn('company_id', $schoolIds)->count(),
        ];
    }

    private function exportColumns(string $tab, $companyNames): array
    {
        $school = fn($row) => $companyNames[$row->company_id] ?? '-';
        $hm = fn($t) => $t instanceof \DateTimeInterface ? $t->format('H:i') : substr((string) $t, 0, 5);

        return match ($tab) {
            'subjects' => [
                ['Sekolah', 'Kode', 'Nama mapel', 'Kelompok', 'Tingkat min', 'Tingkat maks', 'Status', 'Di kurikulum', 'Jadwal', 'Nilai', 'Materi', 'Tugas'],
                fn($s) => [
                    $school($s),
                    $s->code,
                    $s->name,
                    self::SUBJECT_GROUPS[$s->kelompok] ?? $s->kelompok,
                    $s->grade_level_min,
                    $s->grade_level_max,
                    $s->is_active ? 'Aktif' : 'Nonaktif',
                    $s->curriculum_subjects_count,
                    $s->lesson_schedules_count,
                    $s->grades_count,
                    $s->learning_materials_count,
                    $s->assignments_count
                ],
            ],
            'curricula' => [
                ['Sekolah', 'Kurikulum', 'Tahun ajaran', 'Jumlah mapel', 'Total jam/minggu', 'Status', 'Keterangan'],
                fn($c) => [$school($c), $c->name, $c->academic_year, $c->curriculum_subjects_count, (int) $c->total_hours, $c->is_active ? 'Aktif' : 'Nonaktif', $c->description],
            ],
            'schedules' => [
                ['Sekolah', 'Tahun ajaran', 'Kelas', 'Hari', 'Mulai', 'Selesai', 'Mapel', 'Guru', 'Bentrok kelas', 'Bentrok guru'],
                fn($s) => [
                    $school($s),
                    $s->academic_year,
                    $s->classRoom->name ?? '',
                    self::DAYS[$s->hari] ?? $s->hari,
                    $hm($s->jam_mulai),
                    $hm($s->jam_selesai),
                    $s->subject->name ?? '',
                    $s->teacher->name ?? '',
                    $s->class_conflicts ? 'Ya' : '',
                    $s->teacher_conflicts ? 'Ya' : ''
                ],
            ],
            'events' => [
                ['Sekolah', 'Judul', 'Jenis', 'Mulai', 'Selesai', 'Kelas', 'Tahun ajaran', 'Dibuat oleh', 'Keterangan'],
                fn($e) => [
                    $school($e),
                    $e->title,
                    self::EVENT_TYPES[$e->jenis] ?? $e->jenis,
                    optional($e->tanggal_mulai)->format('Y-m-d'),
                    optional($e->tanggal_selesai)->format('Y-m-d'),
                    $e->classRoom->name ?? 'Semua kelas',
                    $e->academic_year,
                    $e->createdBy->name ?? '',
                    $e->description
                ],
            ],
            'grades' => [
                ['Sekolah', 'Tanggal', 'Murid', 'NIS', 'Kelas', 'Mapel', 'Jenis', 'Judul', 'Nilai', 'Semester', 'Tahun ajaran', 'Guru', 'Catatan'],
                fn($g) => [
                    $school($g),
                    optional($g->tanggal)->format('Y-m-d'),
                    $g->student->name ?? '',
                    $g->student->nis ?? '',
                    $g->classRoom->name ?? '',
                    $g->subject->name ?? '',
                    self::GRADE_TYPES[$g->jenis] ?? $g->jenis,
                    $g->judul,
                    (float) $g->nilai,
                    self::SEMESTERS[$g->semester] ?? $g->semester,
                    $g->academic_year,
                    $g->teacher->name ?? '',
                    $g->catatan
                ],
            ],
            'promotions' => [
                ['Sekolah', 'Murid', 'NIS', 'Dari kelas', 'Tahun asal', 'Ke kelas', 'Tahun tujuan', 'Hasil', 'Diproses oleh', 'Tanggal'],
                fn($p) => [
                    $school($p),
                    $p->student->name ?? '',
                    $p->student->nis ?? '',
                    $p->fromClass->name ?? '',
                    $p->from_academic_year,
                    $p->toClass->name ?? '',
                    $p->to_academic_year,
                    self::PROMOTION_STATUS[$p->status] ?? $p->status,
                    $p->processedBy->name ?? '',
                    optional($p->created_at)->format('Y-m-d')
                ],
            ],
            'materials' => [
                ['Sekolah', 'Judul', 'Kelas', 'Mapel', 'Guru', 'File', 'Dibuat'],
                fn($m) => [$school($m), $m->title, $m->classRoom->name ?? '', $m->subject->name ?? '', $m->teacher->name ?? '', $m->file_name, optional($m->created_at)->format('Y-m-d')],
            ],
            'assignments' => [
                ['Sekolah', 'Judul', 'Kelas', 'Mapel', 'Guru', 'Batas waktu', 'Murid di kelas', 'Mengumpulkan', 'Belum dikoreksi'],
                fn($a) => [
                    $school($a),
                    $a->title,
                    $a->classRoom->name ?? '',
                    $a->subject->name ?? '',
                    $a->teacher->name ?? '',
                    optional($a->deadline)->format('Y-m-d H:i'),
                    (int) $a->class_size,
                    $a->submissions_count,
                    $a->ungraded_count
                ],
            ],
        };
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function subjectUsage(int $subjectId): array
    {
        $result = [];
        foreach (self::SUBJECT_USAGE as $table => $label) {
            $result[$table] = DB::table($table)->where('subject_id', $subjectId)->count();
        }

        return $result;
    }

    private function describe(string $tab, $m): string
    {
        $date = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '-';

        return match ($tab) {
            'subjects'    => "Mapel {$m->name}",
            'curricula'   => "Kurikulum {$m->name} ({$m->academic_year})",
            'schedules'   => 'Jadwal ' . ($m->subject->name ?? 'mapel') . ' kelas ' . ($m->classRoom->name ?? '-') . ' hari ' . (self::DAYS[$m->hari] ?? $m->hari),
            'events'      => "Kegiatan kalender \"{$m->title}\"",
            'grades'      => 'Nilai ' . ($m->subject->name ?? '') . ' "' . $m->judul . '" murid ' . ($m->student->name ?? '-'),
            'promotions'  => 'Riwayat kenaikan kelas murid ' . ($m->student->name ?? '-') . " ({$m->from_academic_year} → {$m->to_academic_year})",
            'materials'   => "Materi \"{$m->title}\"",
            'assignments' => "Tugas \"{$m->title}\" (batas {$date($m->deadline)})",
        };
    }

    private function schools()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name', 'type']);
    }

    /** URL file di storage publik (materi, tugas, jawaban tugas) */
    public static function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }

    private function deleteFile(?string $path): void
    {
        if (!$path || preg_match('#^https?://#i', $path)) {
            return;
        }

        try {
            Storage::disk('public')->delete(ltrim($path, '/'));
        } catch (\Throwable) {
            // file sudah tidak ada — abaikan
        }
    }
}
