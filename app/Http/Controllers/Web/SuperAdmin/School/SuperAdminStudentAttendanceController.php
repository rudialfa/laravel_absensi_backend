<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Enums\School\AttendanceStatus;
use App\Enums\School\PermissionStatus;
use App\Enums\School\PermissionType;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AuditLog;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Monitoring Absen Murid (student_attendances) & Izin/Sakit Murid (student_permissions) semua sekolah.
 *
 * Absen diisi lewat kiosk / guru, izin diajukan wali & diproses admin/guru sekolah.
 * Superadmin: memantau, rekap kepatuhan absen per sekolah & kelas, export, dan menghapus data
 * yang salah/ganda (wajib alasan, foto/lampiran ikut terhapus, tercatat di Audit Log).
 */
class SuperAdminStudentAttendanceController extends Controller
{
    public const TABS = [
        'attendances' => 'Absen Harian',
        'recap'       => 'Rekap per Sekolah',
        'permissions' => 'Izin / Sakit',
    ];

    public const STATUS_BADGE = [
        'hadir'     => 'success',
        'terlambat' => 'warning',
        'izin'      => 'info',
        'sakit'     => 'primary',
        'alpa'      => 'danger',
    ];

    public const PERMISSION_BADGE = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
    ];

    /** Rentang tanggal maksimal sekali tampil/export, supaya query tetap ringan */
    private const MAX_RANGE_DAYS = 366;

    // =========================================================
    // INDEX (?tab=attendances|recap|permissions)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'attendances';
        [$from, $to] = $this->range($request);

        $common = [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'from'         => $from,
            'to'           => $to,
            'schools'      => $this->schools(),
            'companyNames' => Company::pluck('name', 'id'),
            'classOptions' => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->orderByDesc('is_active')->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'academic_year', 'is_active'])
                : collect(),
            'statuses'     => self::enumOptions(AttendanceStatus::class),
            'badge'        => self::STATUS_BADGE,
        ];

        return match ($tab) {
            'recap'       => $this->recapTab($request, $common),
            'permissions' => $this->permissionsTab($request, $common),
            default       => $this->attendancesTab($request, $common),
        };
    }

    private function attendancesTab(Request $request, array $common)
    {
        $query = $this->attendanceQuery($request, $common['from'], $common['to']);

        $items = (clone $query)
            ->with([
                'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'deleted_at'),
                'classRoom:id,name',
                'device:id,name',
                'recordedBy:id,name',
            ])
            ->orderByDesc('date')->orderByDesc('check_in_time')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        // Ringkasan status mengikuti semua filter kecuali filter status itu sendiri
        $byStatus = $this->attendanceQuery($request, $common['from'], $common['to'], withStatus: false)
            ->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('pages.superadmin.school.attendances.index', $common + [
            'items'    => $items,
            'byStatus' => $byStatus,
            'devices'  => $request->company_id
                ? AttendanceDevice::where('company_id', $request->company_id)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    private function recapTab(Request $request, array $common)
    {
        [$from, $to] = [$common['from'], $common['to']];
        $schoolIds = $request->company_id ? [(int) $request->company_id] : $common['schools']->pluck('id')->all();

        // Murid aktif per sekolah / per kelas
        $studentsPer = Student::whereIn('company_id', $schoolIds)->where('is_active', true)
            ->selectRaw($request->company_id ? 'class_id as k, count(*) as total' : 'company_id as k, count(*) as total')
            ->groupBy('k')->pluck('total', 'k');

        // Absen per sekolah / per kelas dalam rentang
        $group = $request->company_id ? 'class_id' : 'company_id';
        $rows = StudentAttendance::whereIn('company_id', $schoolIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->toBase()
            ->selectRaw("{$group} as k,
                count(*) as total,
                sum(status = 'hadir') as hadir,
                sum(status = 'terlambat') as terlambat,
                sum(status = 'izin') as izin,
                sum(status = 'sakit') as sakit,
                sum(status = 'alpa') as alpa,
                sum(device_id is not null) as via_kiosk,
                count(distinct date) as days,
                max(date) as last_date")
            ->groupBy('k')->get()->keyBy('k');

        // Absen hari ini per sekolah / kelas (untuk "belum diabsen hari ini")
        $today = StudentAttendance::whereIn('company_id', $schoolIds)->where('date', today()->toDateString())
            ->toBase()->selectRaw("{$group} as k, count(*) as total")->groupBy('k')->pluck('total', 'k');

        if ($request->company_id) {
            $entities = ClassRoom::where('company_id', $request->company_id)
                ->where(fn($q) => $q->where('is_active', true)->orWhereIn('id', $rows->keys()))
                ->orderBy('grade_level')->orderBy('name')
                ->get(['id', 'name', 'academic_year', 'is_active'])
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'sub' => $c->academic_year . ($c->is_active ? '' : ' · arsip')]);
        } else {
            $entities = $common['schools']->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'sub' => $s->type === 'pesantren' ? 'Pesantren' : 'Sekolah', 'is_active' => $s->is_active]);
        }

        $recap = $entities->map(function ($e) use ($rows, $studentsPer, $today) {
            $r = $rows[$e['id']] ?? null;
            $total = (int) ($r->total ?? 0);
            $present = (int) ($r->hadir ?? 0) + (int) ($r->terlambat ?? 0);

            return $e + [
                'students'   => (int) ($studentsPer[$e['id']] ?? 0),
                'total'      => $total,
                'hadir'      => (int) ($r->hadir ?? 0),
                'terlambat'  => (int) ($r->terlambat ?? 0),
                'izin'       => (int) ($r->izin ?? 0),
                'sakit'      => (int) ($r->sakit ?? 0),
                'alpa'       => (int) ($r->alpa ?? 0),
                'via_kiosk'  => (int) ($r->via_kiosk ?? 0),
                'days'       => (int) ($r->days ?? 0),
                'last_date'  => $r->last_date ?? null,
                'rate'       => $total ? round($present / $total * 100, 1) : null,
                'today'      => (int) ($today[$e['id']] ?? 0),
            ];
        })->values();

        // Hari sekolah dalam rentang (Senin–Sabtu) — perkiraan untuk kolom "hari tercatat"
        $schoolDays = collect(range(0, $from->diffInDays($to)))
            ->filter(fn($i) => !$from->copy()->addDays($i)->isSunday())->count();

        return view('pages.superadmin.school.attendances.index', $common + [
            'recap'      => $recap,
            'schoolDays' => $schoolDays,
            'perClass'   => (bool) $request->company_id,
        ]);
    }

    private function permissionsTab(Request $request, array $common)
    {
        $query = $this->permissionQuery($request, $common['from'], $common['to']);

        $items = (clone $query)
            ->with([
                'student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis', 'class_id', 'company_id', 'deleted_at')->with('classRoom:id,name'),
                'submittedBy:id,name',
                'reviewedBy:id,name',
            ])
            ->orderByRaw("status = 'pending' DESC")
            ->orderByDesc('date_permission')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        $base = $this->permissionQuery($request, $common['from'], $common['to'], withStatus: false);
        $summary = (clone $base)->toBase()->selectRaw("
                count(*) as total,
                sum(status = 'pending') as pending,
                sum(status = 'approved') as approved,
                sum(status = 'rejected') as rejected,
                sum(type = 'sakit') as sakit
            ")->first();

        // Pending yang tanggal izinnya sudah lewat (tidak dipedulikan sekolah) — tanpa filter tanggal
        $stale = $this->permissionQuery($request, null, null, withStatus: false)
            ->where('status', 'pending')->whereDate('date_permission', '<', today())->count();

        return view('pages.superadmin.school.attendances.index', $common + [
            'items'          => $items,
            'summary'        => $summary,
            'stale'          => $stale,
            'permStatuses'   => self::enumOptions(PermissionStatus::class),
            'permTypes'      => self::enumOptions(PermissionType::class),
            'permBadge'      => self::PERMISSION_BADGE,
        ]);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show(string $tab, $id)
    {
        if ($tab === 'permissions') {
            $item = StudentPermission::with([
                'student' => fn($q) => $q->withTrashed()->with(['classRoom:id,name,academic_year', 'company:id,name']),
                'submittedBy:id,name,email,phone,role',
                'reviewedBy:id,name,role',
            ])->findOrFail($id);

            // Absen murid di tanggal izin (kalau disetujui, otomatis tercatat izin/sakit)
            $attendance = StudentAttendance::with(['recordedBy:id,name', 'device:id,name'])
                ->where('student_id', $item->student_id)
                ->whereDate('date', $item->date_permission)
                ->first();

            return view('pages.superadmin.school.attendances.show-permission', [
                'item'         => $item,
                'attendance'   => $attendance,
                'fileUrl'      => self::publicFileUrl($item->attachment),
                'permStatuses' => self::enumOptions(PermissionStatus::class),
                'permTypes'    => self::enumOptions(PermissionType::class),
                'permBadge'    => self::PERMISSION_BADGE,
                'statuses'     => self::enumOptions(AttendanceStatus::class),
                'badge'        => self::STATUS_BADGE,
                'history'      => StudentPermission::where('student_id', $item->student_id)->where('id', '!=', $item->id)
                    ->latest('date_permission')->limit(8)->get(['id', 'date_permission', 'type', 'status']),
            ]);
        }

        abort_unless($tab === 'attendances', 404);

        $item = StudentAttendance::with([
            'student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'),
            'classRoom:id,name,academic_year,homeroom_teacher_id',
            'classRoom.homeroomTeacher:id,name',
            'company:id,name',
            'device:id,name,class_id,is_active',
            'recordedBy:id,name,role',
        ])->findOrFail($id);

        $permission = StudentPermission::where('student_id', $item->student_id)
            ->whereDate('date_permission', $item->date)
            ->latest('id')->first();

        $from = Carbon::parse($item->date)->subDays(13);
        $history = StudentAttendance::where('student_id', $item->student_id)
            ->whereBetween('date', [$from->toDateString(), Carbon::parse($item->date)->toDateString()])
            ->orderBy('date')->get(['id', 'date', 'status', 'check_in_time'])
            ->keyBy(fn($a) => $a->date->toDateString());

        return view('pages.superadmin.school.attendances.show-attendance', [
            'item'       => $item,
            'photoUrl'   => self::photoUrl($item->photo_evidence),
            'permission' => $permission,
            'history'    => $history,
            'historyFrom' => $from,
            'statuses'   => self::enumOptions(AttendanceStatus::class),
            'badge'      => self::STATUS_BADGE,
            'permBadge'  => self::PERMISSION_BADGE,
            'permStatuses' => self::enumOptions(PermissionStatus::class),
            'lateThreshold' => config('school.late_threshold', '07:15:00'),
        ]);
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, string $tab, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        if ($tab === 'permissions') {
            $item = StudentPermission::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'company_id')])->findOrFail($id);
            $snapshot = $item->getAttributes();
            $file = $item->attachment;

            DB::transaction(fn() => $item->delete());
            $this->deletePublicFile($file);

            AuditLog::record(
                'delete_student_permission',
                null,
                "Izin {$item->type} murid {$item->student?->name} tanggal " . optional($item->date_permission)->format('Y-m-d') . " (tenant #{$item->student?->company_id}) dihapus. Alasan: {$data['reason']}",
                $snapshot + ['reason' => $data['reason']]
            );

            return redirect()->route('superadmin.school.attendances.index', ['tab' => 'permissions', 'company_id' => $item->student?->company_id])
                ->with('success', 'Pengajuan izin dihapus. Absen yang sudah tercatat dari izin ini tidak ikut terhapus.');
        }

        abort_unless($tab === 'attendances', 404);

        $item = StudentAttendance::with(['student' => fn($q) => $q->withTrashed()->select('id', 'name')])->findOrFail($id);
        $snapshot = $item->getAttributes();
        $photo = $item->photo_evidence;

        DB::transaction(fn() => $item->delete());
        $this->deletePhoto($photo);

        $status = $item->status instanceof \BackedEnum ? $item->status->value : $item->status;

        AuditLog::record(
            'delete_student_attendance',
            null,
            "Absen {$status} murid {$item->student?->name} tanggal " . optional($item->date)->format('Y-m-d') . " (tenant #{$item->company_id}) dihapus. Alasan: {$data['reason']}",
            $snapshot + ['reason' => $data['reason']]
        );

        return redirect()->route('superadmin.school.attendances.index', ['tab' => 'attendances', 'company_id' => $item->company_id])
            ->with('success', 'Data absen dihapus. Murid bisa diabsen ulang untuk tanggal tersebut.');
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'attendances';
        [$from, $to] = $this->range($request);
        $companyNames = Company::pluck('name', 'id');
        $statuses = self::enumOptions(AttendanceStatus::class);

        AuditLog::record('export_student_' . $tab, null, 'Export ' . self::TABS[$tab] . ' murid ke CSV', ['filters' => $request->query()]);

        // Rekap memakai tampilan yang sama dengan halaman
        if ($tab === 'recap') {
            $view = $this->recapTab($request, ['from' => $from, 'to' => $to, 'schools' => $this->schools()]);
            $recap = $view->getData()['recap'];

            return response()->streamDownload(function () use ($recap, $request) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, [$request->company_id ? 'Kelas' : 'Sekolah', 'Keterangan', 'Murid aktif', 'Catatan absen', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Lewat kiosk', 'Hari tercatat', 'Kehadiran %', 'Absen terakhir'], ';');
                foreach ($recap as $r) {
                    fputcsv($out, [$r['name'], $r['sub'], $r['students'], $r['total'], $r['hadir'], $r['terlambat'], $r['izin'], $r['sakit'], $r['alpa'], $r['via_kiosk'], $r['days'], $r['rate'], $r['last_date']], ';');
                }
                fclose($out);
            }, 'rekap-absen-murid-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($tab, $request, $from, $to, $companyNames, $statuses) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($tab === 'permissions') {
                $permStatuses = self::enumOptions(PermissionStatus::class);
                fputcsv($out, ['Sekolah', 'Tanggal izin', 'Murid', 'NIS', 'Kelas', 'Jenis', 'Alasan', 'Lampiran', 'Diajukan oleh', 'Status', 'Diproses oleh', 'Diproses pada', 'Diajukan pada'], ';');
                $this->permissionQuery($request, $from, $to)
                    ->with(['student' => fn($q) => $q->withTrashed()->with('classRoom:id,name'), 'submittedBy:id,name', 'reviewedBy:id,name'])
                    ->orderBy('date_permission')
                    ->chunk(500, function ($rows) use ($out, $companyNames, $permStatuses) {
                        foreach ($rows as $p) {
                            fputcsv($out, [
                                $companyNames[$p->student->company_id ?? 0] ?? '-',
                                optional($p->date_permission)->format('Y-m-d'),
                                $p->student->name ?? '-',
                                $p->student->nis ?? '',
                                $p->student->classRoom->name ?? '',
                                ucfirst($p->type),
                                $p->reason,
                                $p->attachment ? 'Ada' : '',
                                $p->submittedBy->name ?? '',
                                $permStatuses[$p->status] ?? $p->status,
                                $p->reviewedBy->name ?? '',
                                optional($p->reviewed_at)->format('Y-m-d H:i'),
                                optional($p->created_at)->format('Y-m-d H:i'),
                            ], ';');
                        }
                    });
            } else {
                fputcsv($out, ['Sekolah', 'Tanggal', 'Jam', 'Murid', 'NIS', 'Kelas', 'Status', 'Sumber', 'Kiosk / Pencatat', 'Foto', 'Catatan'], ';');
                $this->attendanceQuery($request, $from, $to)
                    ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis'), 'classRoom:id,name', 'device:id,name', 'recordedBy:id,name'])
                    ->orderBy('date')->orderBy('company_id')->orderBy('class_id')
                    ->chunk(1000, function ($rows) use ($out, $companyNames, $statuses) {
                        foreach ($rows as $a) {
                            $st = $a->status instanceof \BackedEnum ? $a->status->value : $a->status;
                            fputcsv($out, [
                                $companyNames[$a->company_id] ?? '-',
                                optional($a->date)->format('Y-m-d'),
                                $a->check_in_time ? substr($a->check_in_time, 0, 5) : '',
                                $a->student->name ?? '-',
                                $a->student->nis ?? '',
                                $a->classRoom->name ?? '',
                                $statuses[$st] ?? $st,
                                $a->device_id ? 'Kiosk' : 'Manual',
                                $a->device->name ?? ($a->recordedBy->name ?? ''),
                                $a->photo_evidence ? 'Ada' : '',
                                $a->notes,
                            ], ';');
                        }
                    });
            }

            fclose($out);
        }, 'absen-murid-' . $tab . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // QUERY
    // =========================================================

    /** Rentang tanggal: default 7 hari terakhir (30 hari kalau mencari murid tertentu) */
    private function range(Request $request): array
    {
        try {
            $to = $request->date_to ? Carbon::parse($request->date_to)->startOfDay() : today();
        } catch (\Throwable) {
            $to = today();
        }

        $defaultDays = $request->search ? 29 : 6;

        try {
            $from = $request->date_from ? Carbon::parse($request->date_from)->startOfDay() : $to->copy()->subDays($defaultDays);
        } catch (\Throwable) {
            $from = $to->copy()->subDays($defaultDays);
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $from = $to->copy()->subDays(self::MAX_RANGE_DAYS);
        }

        return [$from, $to];
    }

    private function attendanceQuery(Request $request, Carbon $from, Carbon $to, bool $withStatus = true)
    {
        return StudentAttendance::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
            ->when($request->device_id, fn($q, $id) => $q->where('device_id', $id))
            ->when($request->source === 'kiosk', fn($q) => $q->whereNotNull('device_id'))
            ->when($request->source === 'manual', fn($q) => $q->whereNull('device_id'))
            ->when($request->photo === '1', fn($q) => $q->whereNotNull('photo_evidence'))
            ->when($request->photo === '0', fn($q) => $q->whereNull('photo_evidence'))
            ->when($withStatus && $request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->whereIn('student_id', Student::withTrashed()->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like)->orWhere('nisn', 'like', $like))->select('id'));
            });
    }

    private function permissionQuery(Request $request, ?Carbon $from, ?Carbon $to, bool $withStatus = true)
    {
        // student_permissions tidak punya company_id → filter sekolah lewat tabel students
        $studentScope = Student::withTrashed()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id, fn($q, $id) => $q->where('class_id', $id))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('nis', 'like', $like)->orWhere('nisn', 'like', $like));
            });

        return StudentPermission::query()
            ->whereIn('student_id', $studentScope->select('id'))
            ->when($from && $to, fn($q) => $q->whereBetween('date_permission', [$from->toDateString(), $to->toDateString()]))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($withStatus && $request->status === 'stale', fn($q) => $q->where('status', 'pending')->whereDate('date_permission', '<', today()))
            ->when($withStatus && $request->status && $request->status !== 'stale', fn($q) => $q->where('status', $request->status))
            ->when($request->attachment === '1', fn($q) => $q->whereNotNull('attachment'));
    }

    private function schools()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name', 'type', 'is_active']);
    }

    // =========================================================
    // FILE
    // =========================================================

    /**
     * URL foto bukti absen. Foto kiosk ada di disk "attendance_photos";
     * data lama/seed bisa berupa path di public/ atau storage publik.
     */
    public static function photoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        try {
            if (Storage::disk('attendance_photos')->exists($path)) {
                return Storage::disk('attendance_photos')->url($path);
            }
        } catch (\Throwable) {
            // disk belum dikonfigurasi → coba lokasi lain
        }

        return self::publicFileUrl($path);
    }

    /** URL file di storage publik (lampiran izin) atau di folder public/ */
    public static function publicFileUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');
        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    private function deletePhoto(?string $path): void
    {
        if (!$path || preg_match('#^https?://#i', $path)) {
            return;
        }

        try {
            if (Storage::disk('attendance_photos')->exists($path)) {
                Storage::disk('attendance_photos')->delete($path);

                return;
            }
        } catch (\Throwable) {
        }

        $this->deletePublicFile($path);
    }

    private function deletePublicFile(?string $path): void
    {
        if (!$path || preg_match('#^https?://#i', $path)) {
            return;
        }

        $path = ltrim($path, '/');
        $full = public_path($path);

        if (is_file($full) && str_starts_with(realpath($full), realpath(public_path()))) {
            @unlink($full);

            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** [value => label] dari enum sekolah */
    public static function enumOptions(string $enum): array
    {
        return collect($enum::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()])->all();
    }
}
