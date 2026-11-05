<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Enums\School\AttendanceStatus;
use App\Enums\School\ClassTeacherRole;
use App\Enums\School\Gender;
use App\Enums\School\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AuditLog;
use App\Models\ClassRoom;
use App\Models\ClassTeacher;
use App\Models\Company;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Kelas, Murid, Wali Murid & Guru Kelas untuk semua tenant sekolah/pesantren.
 *
 * Data ini dikelola Admin Sekolah lewat aplikasi. Superadmin:
 *  - melihat & mencari lintas sekolah, detail kelas & murid, export CSV;
 *  - menemukan data bermasalah (murid tanpa kelas/wali, kelas tanpa wali kelas, akun wali tanpa anak);
 *  - memperbaiki data yang salah: arsip/aktifkan kelas, hapus kelas kosong, hapus & pulihkan murid
 *    (soft delete), lepas hubungan wali–murid dan guru–kelas.
 * Semua aksi mengubah data wajib alasan dan tercatat di Audit Log.
 */
class SuperAdminSchoolClassController extends Controller
{
    public const TABS = [
        'classes'   => 'Kelas',
        'students'  => 'Murid',
        'guardians' => 'Wali Murid',
        'teachers'  => 'Guru Kelas',
    ];

    /** Jenis tenant yang memakai modul sekolah */
    public const SCHOOL_TYPES = ['school', 'pesantren'];

    /** Tabel yang menyimpan class_id. Kelas yang masih dipakai di sini tidak boleh dihapus (sebagian cascade). */
    private const CLASS_DEPENDENTS = [
        'students'           => 'murid',
        'student_attendances' => 'data absen',
        'lesson_schedules'   => 'jadwal pelajaran',
        'grades'             => 'nilai',
        'assignments'        => 'tugas',
        'learning_materials' => 'materi',
        'academic_events'    => 'kalender akademik',
        'attendance_devices' => 'perangkat kiosk',
    ];

    // =========================================================
    // INDEX (?tab=classes|students|guardians|teachers)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'classes';

        $schools = Company::whereIn('type', self::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name', 'type']);

        $common = [
            'tab'           => $tab,
            'tabs'          => self::TABS,
            'schools'       => $schools,
            'companyNames'  => Company::pluck('name', 'id'),
            'academicYears' => ClassRoom::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            // Pilihan kelas hanya muncul kalau sekolah sudah dipilih (supaya daftar tidak campur)
            'classOptions'  => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'academic_year', 'is_active'])
                : collect(),
        ];

        return match ($tab) {
            'students'  => $this->studentsTab($request, $common),
            'guardians' => $this->guardiansTab($request, $common),
            'teachers'  => $this->teachersTab($request, $common),
            default     => $this->classesTab($request, $common),
        };
    }

    private function classesTab(Request $request, array $common)
    {
        $query = $this->classQuery($request);
        $today = now()->toDateString();

        $items = (clone $query)
            ->with('homeroomTeacher:id,name,is_active')
            ->withCount([
                'students as students_count' => fn($q) => $q->where('is_active', true),
                'classTeachers',
                'attendanceDevices',
                'studentAttendances as today_present' => fn($q) => $q->where('date', $today)->whereIn('status', ['hadir', 'terlambat']),
                'studentAttendances as today_recorded' => fn($q) => $q->where('date', $today),
            ])
            ->orderBy('company_id')->orderByDesc('is_active')->orderBy('grade_level')->orderBy('name')
            ->paginate(25)->withQueryString();

        $summary = (clone $query)->toBase()->selectRaw('
                count(*) as total,
                sum(is_active = 1) as active,
                sum(is_active = 1 and homeroom_teacher_id is null) as no_homeroom
            ')->first();

        $studentsActive = Student::whereIn('class_id', (clone $query)->select('id'))->where('is_active', true)->count();

        return view('pages.superadmin.school.classes.index', $common + [
            'items'          => $items,
            'summary'        => $summary,
            'studentsActive' => $studentsActive,
        ]);
    }

    private function studentsTab(Request $request, array $common)
    {
        $query = $this->studentQuery($request);

        $items = (clone $query)
            ->with('classRoom:id,name,academic_year')
            ->withCount('guardians')
            ->orderBy('company_id')->orderBy('name')
            ->paginate(25)->withQueryString();

        // Ringkasan dihitung dari filter sekolah/kelas/cari saja (tanpa filter status/masalah)
        $base = $this->studentQuery($request, withStatus: false);
        $summary = [
            'total'       => (clone $base)->count(),
            'active'      => (clone $base)->where('is_active', true)->count(),
            'boarding'    => (clone $base)->where('is_active', true)->where('is_boarding', true)->count(),
            'no_class'    => (clone $base)->where('is_active', true)->whereNull('class_id')->count(),
            'no_guardian' => (clone $base)->where('is_active', true)->whereDoesntHave('studentGuardians')->count(),
            'trashed'     => (clone $base)->onlyTrashed()->count(),
        ];

        return view('pages.superadmin.school.classes.index', $common + [
            'items'   => $items,
            'summary' => $summary,
            'genders' => $this->enumOptions(Gender::class),
        ]);
    }

    private function guardiansTab(Request $request, array $common)
    {
        $query = $this->guardianQuery($request);

        $items = (clone $query)
            ->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.company_id', 'users.is_active', 'users.created_at')
            ->selectSub(fn($q) => $q->from('student_guardians')->whereColumn('student_guardians.user_id', 'users.id')->selectRaw('count(*)'), 'children_count')
            ->orderBy('company_id')->orderBy('name')
            ->paginate(25)->withQueryString();

        // Nama anak per wali (hanya untuk wali di halaman ini)
        $children = StudentGuardian::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'class_id', 'deleted_at')->with('classRoom:id,name')])
            ->whereIn('user_id', $items->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $base = $this->guardianQuery($request, withIssue: false);
        $summary = [
            'total'     => (clone $base)->count(),
            'active'    => (clone $base)->where('users.is_active', true)->count(),
            'no_child'  => (clone $base)->whereNotExists(fn($q) => $q->from('student_guardians')->whereColumn('student_guardians.user_id', 'users.id'))->count(),
            'links'     => StudentGuardian::whereIn('user_id', (clone $base)->select('users.id'))->count(),
        ];

        return view('pages.superadmin.school.classes.index', $common + [
            'items'         => $items,
            'summary'       => $summary,
            'children'      => $children,
            'relationships' => $this->enumOptions(GuardianRelationship::class),
        ]);
    }

    private function teachersTab(Request $request, array $common)
    {
        $query = $this->teacherQuery($request);

        $items = (clone $query)
            ->with(['classRoom:id,name,company_id,academic_year,is_active,homeroom_teacher_id', 'teacher:id,name,email,role,is_active'])
            ->orderBy(ClassRoom::select('company_id')->whereColumn('class_rooms.id', 'class_teachers.class_id'))
            ->orderBy('class_id')->orderByRaw("role_in_class = 'wali_kelas' DESC")
            ->paginate(25)->withQueryString();

        $schoolIds = $request->company_id ? [$request->company_id] : Company::whereIn('type', self::SCHOOL_TYPES)->pluck('id');

        $summary = [
            'total'        => (clone $query)->count(),
            'homeroom'     => (clone $query)->where('role_in_class', ClassTeacherRole::WaliKelas->value)->count(),
            'subject'      => (clone $query)->where('role_in_class', ClassTeacherRole::GuruMapel->value)->count(),
            // Akun guru yang belum dihubungkan ke kelas manapun
            'idle_teachers' => User::where('role', 'guru')->whereIn('company_id', $schoolIds)->where('is_active', true)
                ->whereNotExists(fn($q) => $q->from('class_teachers')->whereColumn('class_teachers.user_id', 'users.id'))
                ->count(),
        ];

        return view('pages.superadmin.school.classes.index', $common + [
            'items'     => $items,
            'summary'   => $summary,
            'roleLabels' => $this->enumOptions(ClassTeacherRole::class),
        ]);
    }

    // =========================================================
    // DETAIL KELAS
    // =========================================================
    public function showClass($id)
    {
        $class = ClassRoom::with([
            'company:id,name,type',
            'homeroomTeacher:id,name,email,phone,is_active',
            'classTeachers' => fn($q) => $q->with('teacher:id,name,email,role,is_active')->orderByRaw("role_in_class = 'wali_kelas' DESC"),
            'attendanceDevices' => fn($q) => $q->orderByDesc('last_seen_at'),
        ])->findOrFail($id);

        $today = now()->toDateString();

        $students = Student::where('class_id', $class->id)
            ->withCount('guardians')
            ->with(['todayAttendance:id,student_id,status,check_in_time'])
            ->orderByDesc('is_active')->orderBy('name')
            ->get();

        // Rekap absen 30 hari terakhir per status
        $from = now()->subDays(29)->toDateString();
        $recap = StudentAttendance::where('class_id', $class->id)
            ->whereBetween('date', [$from, $today])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $activeDays = StudentAttendance::where('class_id', $class->id)->whereBetween('date', [$from, $today])->distinct()->count('date');

        return view('pages.superadmin.school.classes.show-class', [
            'class'       => $class,
            'students'    => $students,
            'recap'       => $recap,
            'activeDays'  => $activeDays,
            'statuses'    => $this->enumOptions(AttendanceStatus::class),
            'roleLabels'  => $this->enumOptions(ClassTeacherRole::class),
            'dependents'  => $this->classDependents($class),
            'promotions'  => Schema::hasTable('class_promotions')
                ? DB::table('class_promotions')->where('from_class_id', $class->id)->orWhere('to_class_id', $class->id)->count()
                : 0,
        ]);
    }

    // =========================================================
    // DETAIL MURID (termasuk yang sudah dihapus)
    // =========================================================
    public function showStudent($id)
    {
        $student = Student::withTrashed()
            ->with([
                'company:id,name,type',
                'classRoom:id,name,academic_year,grade_level,homeroom_teacher_id',
                'classRoom.homeroomTeacher:id,name',
                'studentGuardians' => fn($q) => $q->with('guardian:id,name,email,phone,role,company_id,is_active')->orderByDesc('is_primary'),
                'activeRoomAssignment.room.dormitory',
                'healthRecord',
            ])
            ->withCount(['permissions', 'grades', 'bills', 'achievements', 'uksVisits', 'mutations', 'classPromotions', 'extracurricularMemberships', 'tahfidzSetorans', 'boardingPermissions'])
            ->findOrFail($id);

        $from = now()->subDays(29)->toDateString();
        $recap = StudentAttendance::where('student_id', $student->id)
            ->whereBetween('date', [$from, now()->toDateString()])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentAttendances = StudentAttendance::where('student_id', $student->id)
            ->with(['classRoom:id,name', 'recordedBy:id,name', 'device:id,name'])
            ->latest('date')->latest('id')
            ->limit(10)
            ->get();

        return view('pages.superadmin.school.classes.show-student', [
            'student'           => $student,
            'photoUrl'          => self::fileUrl($student->photo_url),
            'recap'             => $recap,
            'recentAttendances' => $recentAttendances,
            'totalAttendances'  => StudentAttendance::where('student_id', $student->id)->count(),
            'statuses'          => $this->enumOptions(AttendanceStatus::class),
            'genders'           => $this->enumOptions(Gender::class),
            'relationships'     => $this->enumOptions(GuardianRelationship::class),
        ]);
    }

    // =========================================================
    // AKSI KELAS
    // =========================================================
    public function toggleClass(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $class = ClassRoom::findOrFail($id);
        $class->forceFill(['is_active' => !$class->is_active])->save();

        $state = $class->is_active ? 'diaktifkan kembali' : 'diarsipkan';

        AuditLog::record(
            $class->is_active ? 'activate_class' : 'archive_class',
            null,
            "Kelas {$class->name} ({$class->academic_year}) tenant #{$class->company_id} {$state}. Alasan: {$data['reason']}",
            ['class_id' => $class->id, 'company_id' => $class->company_id, 'is_active' => $class->is_active, 'reason' => $data['reason']]
        );

        return back()->with('success', "Kelas {$class->name} {$state}.");
    }

    public function destroyClass(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $class = ClassRoom::with('classTeachers')->findOrFail($id);

        $used = array_filter($this->classDependents($class));
        if ($used) {
            $list = collect($used)->map(fn($n, $t) => $n . ' ' . self::CLASS_DEPENDENTS[$t])->implode(', ');

            return back()->with('error', "Kelas {$class->name} masih dipakai ({$list}). Gunakan Arsipkan, bukan Hapus, supaya histori tidak hilang.");
        }

        $snapshot = $class->getAttributes() + ['class_teachers' => $class->classTeachers->map->getAttributes()->all()];

        DB::transaction(fn() => $class->delete()); // class_teachers ikut terhapus (cascade)

        AuditLog::record('delete_class', null, "Kelas kosong {$class->name} ({$class->academic_year}) tenant #{$class->company_id} dihapus. Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return redirect()->route('superadmin.school.classes.index', ['tab' => 'classes', 'company_id' => $class->company_id])
            ->with('success', "Kelas {$class->name} dihapus.");
    }

    // =========================================================
    // AKSI MURID
    // =========================================================
    public function destroyStudent(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $student = Student::findOrFail($id);
        $snapshot = $student->getAttributes();

        // Soft delete: histori absen, nilai & tagihan tetap utuh, dan bisa dipulihkan.
        $student->delete();

        AuditLog::record('delete_student', null, "Murid {$student->name} (NIS {$student->nis}) tenant #{$student->company_id} dihapus. Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return redirect()->route('superadmin.school.students.show', $student->id)
            ->with('success', "Murid {$student->name} dihapus (bisa dipulihkan dari halaman ini).");
    }

    public function restoreStudent(Request $request, $id)
    {
        $student = Student::onlyTrashed()->findOrFail($id);

        // NIS unik per sekolah — cegah bentrok kalau admin sekolah sudah memakai NIS yang sama
        $clash = Student::where('company_id', $student->company_id)->where('nis', $student->nis)->exists();
        if ($clash) {
            return back()->with('error', "NIS {$student->nis} sudah dipakai murid lain di sekolah ini. Ubah NIS murid tersebut dulu sebelum memulihkan.");
        }

        $student->restore();

        AuditLog::record('restore_student', null, "Murid {$student->name} (NIS {$student->nis}) tenant #{$student->company_id} dipulihkan.", ['student_id' => $student->id]);

        return back()->with('success', "Murid {$student->name} dipulihkan.");
    }

    /** Lepas hubungan wali–murid (akun wali tidak dihapus) */
    public function detachGuardian(Request $request, $studentId, $linkId)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $link = StudentGuardian::with(['guardian:id,name', 'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'company_id')])
            ->where('student_id', $studentId)
            ->findOrFail($linkId);

        $snapshot = $link->getAttributes();
        $link->delete();

        AuditLog::record('detach_guardian', null, "Wali {$link->guardian?->name} dilepas dari murid {$link->student?->name} (tenant #{$link->student?->company_id}). Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return back()->with('success', "Hubungan wali {$link->guardian?->name} dengan murid {$link->student?->name} dilepas.");
    }

    // =========================================================
    // AKSI GURU KELAS
    // =========================================================
    public function detachTeacher(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $row = ClassTeacher::with(['classRoom', 'teacher:id,name'])->findOrFail($id);
        $snapshot = $row->getAttributes();

        DB::transaction(function () use ($row) {
            $row->delete();

            // Sinkron dengan aplikasi: kalau wali kelas dilepas, kosongkan homeroom_teacher_id
            $class = $row->classRoom;
            if (
                $class && (int) $class->homeroom_teacher_id === (int) $row->user_id
                && !ClassTeacher::where('class_id', $class->id)->where('user_id', $row->user_id)->where('role_in_class', ClassTeacherRole::WaliKelas->value)->exists()
            ) {
                $class->forceFill(['homeroom_teacher_id' => null])->save();
            }
        });

        AuditLog::record('detach_class_teacher', null, "Guru {$row->teacher?->name} dilepas dari kelas {$row->classRoom?->name} (tenant #{$row->classRoom?->company_id}). Alasan: {$data['reason']}", $snapshot + ['reason' => $data['reason']]);

        return back()->with('success', "Guru {$row->teacher?->name} dilepas dari kelas {$row->classRoom?->name}.");
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'classes';
        $companyNames = Company::pluck('name', 'id');
        $genders = $this->enumOptions(Gender::class);
        $relationships = $this->enumOptions(GuardianRelationship::class);
        $roleLabels = $this->enumOptions(ClassTeacherRole::class);

        AuditLog::record('export_school_' . $tab, null, 'Export ' . self::TABS[$tab] . ' sekolah ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($tab, $request, $companyNames, $genders, $relationships, $roleLabels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            switch ($tab) {
                case 'students':
                    fputcsv($out, ['Sekolah', 'NIS', 'NISN', 'Nama', 'L/P', 'Tempat lahir', 'Tanggal lahir', 'Kelas', 'Tahun ajaran', 'Asrama', 'Jumlah wali', 'Status', 'Terdaftar', 'Dihapus'], ';');
                    $this->studentQuery($request)->with('classRoom:id,name,academic_year')->withCount('guardians')
                        ->orderBy('company_id')->orderBy('name')
                        ->chunk(500, function ($rows) use ($out, $companyNames, $genders) {
                            foreach ($rows as $s) {
                                fputcsv($out, [
                                    $companyNames[$s->company_id] ?? '-',
                                    $s->nis,
                                    $s->nisn,
                                    $s->name,
                                    $genders[$s->gender] ?? $s->gender,
                                    $s->birth_place,
                                    optional($s->birth_date)->format('Y-m-d'),
                                    $s->classRoom->name ?? '',
                                    $s->classRoom->academic_year ?? '',
                                    $s->is_boarding ? 'Ya' : 'Tidak',
                                    $s->guardians_count,
                                    $s->is_active ? 'Aktif' : 'Nonaktif',
                                    optional($s->enrolled_at)->format('Y-m-d'),
                                    optional($s->deleted_at)->format('Y-m-d H:i'),
                                ], ';');
                            }
                        });
                    break;

                case 'guardians':
                    fputcsv($out, ['Sekolah', 'Nama wali', 'Email', 'No. HP', 'Status akun', 'Anak', 'Hubungan'], ';');
                    $this->guardianQuery($request)->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.company_id', 'users.is_active')
                        ->orderBy('company_id')->orderBy('name')
                        ->chunk(500, function ($rows) use ($out, $companyNames, $relationships) {
                            $links = StudentGuardian::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])
                                ->whereIn('user_id', $rows->pluck('id'))->get()->groupBy('user_id');
                            foreach ($rows as $u) {
                                $mine = $links[$u->id] ?? collect();
                                fputcsv($out, [
                                    $companyNames[$u->company_id] ?? '-',
                                    $u->name,
                                    $u->email,
                                    $u->phone,
                                    $u->is_active ? 'Aktif' : 'Nonaktif',
                                    $mine->map(fn($l) => $l->student->name ?? '?')->implode(', '),
                                    $mine->map(fn($l) => $relationships[$l->relationship] ?? $l->relationship)->implode(', '),
                                ], ';');
                            }
                        });
                    break;

                case 'teachers':
                    fputcsv($out, ['Sekolah', 'Kelas', 'Tahun ajaran', 'Guru', 'Email', 'Peran', 'Mapel', 'Sejak'], ';');
                    $this->teacherQuery($request)->with(['classRoom:id,name,company_id,academic_year', 'teacher:id,name,email'])
                        ->orderBy('class_id')
                        ->chunk(500, function ($rows) use ($out, $companyNames, $roleLabels) {
                            foreach ($rows as $r) {
                                fputcsv($out, [
                                    $companyNames[$r->classRoom->company_id ?? 0] ?? '-',
                                    $r->classRoom->name ?? '-',
                                    $r->classRoom->academic_year ?? '',
                                    $r->teacher->name ?? '-',
                                    $r->teacher->email ?? '',
                                    $roleLabels[$r->role_in_class] ?? $r->role_in_class,
                                    $r->subject,
                                    optional($r->created_at)->format('Y-m-d'),
                                ], ';');
                            }
                        });
                    break;

                default:
                    fputcsv($out, ['Sekolah', 'Kelas', 'Tingkat', 'Tahun ajaran', 'Wali kelas', 'Murid aktif', 'Guru', 'Kiosk', 'Status'], ';');
                    $this->classQuery($request)->with('homeroomTeacher:id,name')
                        ->withCount(['students as students_count' => fn($q) => $q->where('is_active', true), 'classTeachers', 'attendanceDevices'])
                        ->orderBy('company_id')->orderBy('grade_level')->orderBy('name')
                        ->chunk(500, function ($rows) use ($out, $companyNames) {
                            foreach ($rows as $c) {
                                fputcsv($out, [
                                    $companyNames[$c->company_id] ?? '-',
                                    $c->name,
                                    $c->grade_level,
                                    $c->academic_year,
                                    $c->homeroomTeacher->name ?? '',
                                    $c->students_count,
                                    $c->class_teachers_count,
                                    $c->attendance_devices_count,
                                    $c->is_active ? 'Aktif' : 'Arsip',
                                ], ';');
                            }
                        });
            }

            fclose($out);
        }, 'sekolah-' . $tab . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY
    // =========================================================
    private function schoolIds()
    {
        return Company::whereIn('type', self::SCHOOL_TYPES)->select('id');
    }

    private function classQuery(Request $request)
    {
        return ClassRoom::query()
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->academic_year, fn($q, $y) => $q->where('academic_year', $y))
            ->when($request->grade_level, fn($q, $g) => $q->where('grade_level', $g))
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'archived', fn($q) => $q->where('is_active', false))
            ->when($request->issue === 'no_homeroom', fn($q) => $q->where('is_active', true)->whereNull('homeroom_teacher_id'))
            ->when($request->issue === 'empty', fn($q) => $q->whereDoesntHave('students'))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'));
    }

    private function studentQuery(Request $request, bool $withStatus = true)
    {
        $query = Student::query()
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
            ->when($request->academic_year && !$request->class_id, fn($q) => $q->whereHas('classRoom', fn($c) => $c->where('academic_year', $request->academic_year)))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like)->orWhere('nisn', 'like', $like));
            });

        if (!$withStatus) {
            return $query;
        }

        return $query
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->status === 'trashed', fn($q) => $q->onlyTrashed())
            ->when($request->gender, fn($q, $g) => $q->where('gender', $g))
            ->when($request->boarding === '1', fn($q) => $q->where('is_boarding', true))
            ->when($request->boarding === '0', fn($q) => $q->where('is_boarding', false))
            ->when($request->issue === 'no_class', fn($q) => $q->where('is_active', true)->whereNull('class_id'))
            ->when($request->issue === 'no_guardian', fn($q) => $q->where('is_active', true)->whereDoesntHave('studentGuardians'));
    }

    private function guardianQuery(Request $request, bool $withIssue = true)
    {
        return User::query()
            ->where('role', 'wali')
            ->whereIn('company_id', $this->schoolIds())
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->whereExists(fn($e) => $e->from('student_guardians')
                ->join('students', 'students.id', '=', 'student_guardians.student_id')
                ->whereColumn('student_guardians.user_id', 'users.id')
                ->where('students.class_id', $id)))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->when($withIssue && $request->issue === 'no_child', fn($q) => $q->whereNotExists(fn($e) => $e->from('student_guardians')->whereColumn('student_guardians.user_id', 'users.id')))
            ->when($withIssue && $request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($withIssue && $request->status === 'inactive', fn($q) => $q->where('is_active', false));
    }

    private function teacherQuery(Request $request)
    {
        return ClassTeacher::query()
            ->whereHas('classRoom', function ($q) use ($request) {
                $q->whereIn('company_id', $this->schoolIds())
                    ->when($request->company_id, fn($c, $id) => $c->where('company_id', $id))
                    ->when($request->academic_year, fn($c, $y) => $c->where('academic_year', $y));
            })
            ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
            ->when($request->role_in_class, fn($q, $r) => $q->where('role_in_class', $r))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('subject', 'like', $like)->orWhereHas('teacher', fn($t) => $t->where('name', 'like', $like)));
            });
    }

    // =========================================================
    // HELPER
    // =========================================================

    /** Jumlah data yang masih memakai kelas ini, per tabel */
    private function classDependents(ClassRoom $class): array
    {
        $result = [];
        foreach (self::CLASS_DEPENDENTS as $table => $label) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'class_id')) {
                continue;
            }
            $result[$table] = DB::table($table)->where('class_id', $class->id)->count();
        }

        return $result;
    }

    /** [value => label] dari enum sekolah */
    private function enumOptions(string $enum): array
    {
        return collect($enum::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()])->all();
    }

    /** URL file/foto: URL penuh, file di public/, atau di storage publik */
    public static function fileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        return file_exists(public_path($path)) ? asset($path) : asset('storage/' . $path);
    }
}
