<?php

namespace App\Http\Controllers\Web\SuperAdmin\School;

use App\Enums\School\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AuditLog;
use App\Models\ClassRoom;
use App\Models\Company;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Perangkat Kiosk (tabel attendance_devices) semua sekolah/pesantren — CRUD lengkap.
 *
 * Kiosk login memakai header X-Device-Token (middleware VerifyDeviceToken), bukan akun user.
 * Token hanya ditampilkan SEKALI: setelah perangkat dibuat atau token dibuat ulang.
 * Menonaktifkan / membuat ulang token langsung memutus kiosk yang sedang dipakai.
 */
class SuperAdminDeviceController extends Controller
{
    /** Kiosk dianggap online kalau ada request dalam rentang ini */
    public const ONLINE_MINUTES = 10;

    /** Kiosk aktif yang tidak terlihat selama ini ditandai "lama tidak aktif" */
    public const IDLE_DAYS = 7;

    public const CONNECTION = [
        'online' => 'Online (10 menit terakhir)',
        'today'  => 'Terhubung hari ini',
        'idle'   => 'Tidak aktif > 7 hari',
        'never'  => 'Belum pernah terhubung',
    ];

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $query = $this->deviceQuery($request);
        $today = now()->toDateString();

        $items = (clone $query)
            ->with(['classRoom:id,name,academic_year', 'registeredBy:id,name'])
            ->withCount([
                'attendances',
                'attendances as today_count' => fn($q) => $q->where('date', $today),
            ])
            ->orderBy('company_id')->orderByDesc('is_active')->orderByDesc('last_seen_at')
            ->paginate(25)->withQueryString();

        $base = $this->deviceQuery($request, withState: false);
        $summary = [
            'total'  => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'online' => (clone $base)->where('is_active', true)->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))->count(),
            'today'  => (clone $base)->where('is_active', true)->where('last_seen_at', '>=', today())->count(),
            'idle'   => (clone $base)->where('is_active', true)->where(fn($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(self::IDLE_DAYS)))->count(),
        ];

        // Sekolah aktif yang belum punya kiosk sama sekali
        $schoolsWithoutDevice = Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)
            ->where('is_active', true)
            ->whereNotIn('id', AttendanceDevice::select('company_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('pages.superadmin.school.devices.index', [
            'items'                => $items,
            'summary'              => $summary,
            'schools'              => $this->schools(),
            'companyNames'         => Company::pluck('name', 'id'),
            'classOptions'         => $request->company_id
                ? ClassRoom::where('company_id', $request->company_id)->orderByDesc('is_active')->orderBy('grade_level')->orderBy('name')->get(['id', 'name', 'academic_year', 'is_active'])
                : collect(),
            'connection'           => self::CONNECTION,
            'schoolsWithoutDevice' => $schoolsWithoutDevice,
        ]);
    }

    // =========================================================
    // CREATE / STORE
    // =========================================================
    public function create(Request $request)
    {
        return view('pages.superadmin.school.devices.create', $this->formData(new AttendanceDevice([
            'company_id' => $request->company_id,
            'class_id'   => $request->class_id,
            'is_active'  => true,
        ])));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $token = $this->newToken();

        $device = new AttendanceDevice();
        $device->forceFill($data + [
            'device_token'  => $token,
            'registered_by' => $request->user()->id,
        ])->save();

        AuditLog::record('create_device', null, "Kiosk {$device->name} didaftarkan untuk tenant #{$device->company_id}", [
            'device_id'  => $device->id,
            'company_id' => $device->company_id,
            'class_id'   => $device->class_id,
        ]);

        return redirect()->route('superadmin.school.devices.show', $device->id)
            ->with('success', 'Kiosk berhasil didaftarkan. Salin token di bawah sekarang — token tidak akan ditampilkan lagi.')
            ->with('device_token', $token);
    }

    // =========================================================
    // SHOW
    // =========================================================
    public function show($id)
    {
        $device = AttendanceDevice::with([
            'company:id,name,type,is_active',
            'classRoom:id,name,academic_year,is_active',
            'registeredBy:id,name,role',
        ])->withCount('attendances')->findOrFail($id);

        // Jumlah absen per hari, 14 hari terakhir
        $from = now()->subDays(13)->startOfDay();
        $perDay = StudentAttendance::where('device_id', $device->id)
            ->where('date', '>=', $from->toDateString())
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->mapWithKeys(fn($v, $k) => [substr((string) $k, 0, 10) => (int) $v]);

        $days = collect(range(0, 13))->map(function ($i) use ($from, $perDay) {
            $d = $from->copy()->addDays($i);

            return ['date' => $d, 'total' => $perDay[$d->toDateString()] ?? 0];
        });

        $recent = StudentAttendance::where('device_id', $device->id)
            ->with(['student' => fn($q) => $q->withTrashed()->select('id', 'name', 'nis'), 'classRoom:id,name'])
            ->latest('date')->latest('id')
            ->limit(15)
            ->get();

        return view('pages.superadmin.school.devices.show', [
            'device'     => $device,
            'days'       => $days,
            'recent'     => $recent,
            'statuses'   => collect(AttendanceStatus::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()])->all(),
            'newToken'   => session('device_token'),
            'state'      => self::state($device),
            'firstUsed'  => StudentAttendance::where('device_id', $device->id)->min('date'),
            'lastUsed'   => StudentAttendance::where('device_id', $device->id)->max('date'),
        ]);
    }

    // =========================================================
    // EDIT / UPDATE
    // =========================================================
    public function edit($id)
    {
        $device = AttendanceDevice::findOrFail($id);

        return view('pages.superadmin.school.devices.edit', $this->formData($device));
    }

    public function update(Request $request, $id)
    {
        $device = AttendanceDevice::findOrFail($id);
        $before = $device->only(['company_id', 'class_id', 'name', 'device_identifier', 'is_active']);

        $data = $this->validated($request, $device);
        $device->forceFill($data)->save();

        $after = $device->only(array_keys($before));
        $changed = array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)));

        AuditLog::record('update_device', null, "Kiosk {$device->name} (tenant #{$device->company_id}) diperbarui", [
            'device_id' => $device->id,
            'before'    => array_intersect_key($before, array_flip($changed)),
            'after'     => array_intersect_key($after, array_flip($changed)),
        ]);

        // Pindah sekolah = token lama tidak boleh dipakai di sekolah baru
        if (in_array('company_id', $changed, true)) {
            $token = $this->newToken();
            $device->forceFill(['device_token' => $token, 'last_seen_at' => null])->save();

            return redirect()->route('superadmin.school.devices.show', $device->id)
                ->with('success', 'Kiosk dipindah ke sekolah lain, token otomatis dibuat ulang. Pasang token baru di perangkat.')
                ->with('device_token', $token);
        }

        return redirect()->route('superadmin.school.devices.show', $device->id)->with('success', 'Kiosk diperbarui.');
    }

    // =========================================================
    // AKSI
    // =========================================================
    public function toggleActive(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $device = AttendanceDevice::findOrFail($id);
        $device->forceFill(['is_active' => !$device->is_active])->save();

        $state = $device->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::record(
            $device->is_active ? 'activate_device' : 'deactivate_device',
            null,
            "Kiosk {$device->name} (tenant #{$device->company_id}) {$state}. Alasan: {$data['reason']}",
            ['device_id' => $device->id, 'reason' => $data['reason']]
        );

        return back()->with('success', "Kiosk {$device->name} {$state}." . ($device->is_active ? '' : ' Kiosk langsung tidak bisa dipakai absen.'));
    }

    public function regenerateToken(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $device = AttendanceDevice::findOrFail($id);
        $token = $this->newToken();
        $device->forceFill(['device_token' => $token])->save();

        AuditLog::record(
            'regenerate_device_token',
            null,
            "Token kiosk {$device->name} (tenant #{$device->company_id}) dibuat ulang. Alasan: {$data['reason']}",
            ['device_id' => $device->id, 'reason' => $data['reason']]
        );

        return redirect()->route('superadmin.school.devices.show', $device->id)
            ->with('success', 'Token baru dibuat. Token lama langsung tidak berlaku. Salin token di bawah sekarang.')
            ->with('device_token', $token);
    }

    public function destroy(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        $device = AttendanceDevice::withCount('attendances')->findOrFail($id);
        $snapshot = collect($device->getAttributes())->except('device_token')->all();

        // student_attendances.device_id = nullOnDelete → histori absen tetap ada, hanya sumber kiosk-nya kosong
        DB::transaction(fn() => $device->delete());

        AuditLog::record(
            'delete_device',
            null,
            "Kiosk {$device->name} (tenant #{$device->company_id}, {$device->attendances_count} absen) dihapus. Alasan: {$data['reason']}",
            $snapshot + ['attendances_count' => $device->attendances_count, 'reason' => $data['reason']]
        );

        return redirect()->route('superadmin.school.devices.index', ['company_id' => $device->company_id])
            ->with('success', "Kiosk {$device->name} dihapus. {$device->attendances_count} data absen dari kiosk ini tetap tersimpan.");
    }

    // =========================================================
    // EXPORT CSV
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $companyNames = Company::pluck('name', 'id');

        AuditLog::record('export_devices', null, 'Export perangkat kiosk ke CSV', ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($request, $companyNames) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Sekolah', 'Nama kiosk', 'Kelas', 'ID perangkat', 'Status', 'Koneksi', 'Terakhir aktif', 'Total absen', 'Didaftarkan oleh', 'Dibuat'], ';');

            $this->deviceQuery($request)->with(['classRoom:id,name', 'registeredBy:id,name'])->withCount('attendances')
                ->orderBy('company_id')->orderBy('name')
                ->chunk(500, function ($rows) use ($out, $companyNames) {
                    foreach ($rows as $d) {
                        fputcsv($out, [
                            $companyNames[$d->company_id] ?? '-',
                            $d->name,
                            $d->classRoom->name ?? 'Umum (semua kelas)',
                            $d->device_identifier,
                            $d->is_active ? 'Aktif' : 'Nonaktif',
                            self::state($d)['label'],
                            optional($d->last_seen_at)->format('Y-m-d H:i'),
                            $d->attendances_count,
                            $d->registeredBy->name ?? '',
                            optional($d->created_at)->format('Y-m-d'),
                        ], ';');
                    }
                });

            fclose($out);
        }, 'kiosk-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // =========================================================
    // HELPER
    // =========================================================

    /** Status koneksi kiosk untuk badge: ['key', 'label', 'badge'] */
    public static function state(AttendanceDevice $device): array
    {
        if (!$device->is_active) {
            return ['key' => 'inactive', 'label' => 'Nonaktif', 'badge' => 'secondary'];
        }
        if (!$device->last_seen_at) {
            return ['key' => 'never', 'label' => 'Belum pernah terhubung', 'badge' => 'light'];
        }
        if ($device->last_seen_at->gte(now()->subMinutes(self::ONLINE_MINUTES))) {
            return ['key' => 'online', 'label' => 'Online', 'badge' => 'success'];
        }
        if ($device->last_seen_at->lt(now()->subDays(self::IDLE_DAYS))) {
            return ['key' => 'idle', 'label' => 'Lama tidak aktif', 'badge' => 'warning'];
        }

        return ['key' => 'offline', 'label' => 'Offline', 'badge' => 'info'];
    }

    private function deviceQuery(Request $request, bool $withState = true)
    {
        $query = AttendanceDevice::query()
            ->whereIn('company_id', Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->select('id'))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->class_id === 'general', fn($q) => $q->whereNull('class_id'))
            ->when($request->class_id && $request->class_id !== 'general', fn($q) => $q->where('class_id', $request->class_id))
            ->when($request->search, function ($q, $s) {
                $like = '%' . addcslashes($s, '%_\\') . '%';
                $q->where(fn($w) => $w->where('name', 'like', $like)->orWhere('device_identifier', 'like', $like));
            });

        if (!$withState) {
            return $query;
        }

        return $query
            ->when($request->status === 'active', fn($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn($q) => $q->where('is_active', false))
            ->when($request->connection === 'online', fn($q) => $q->where('is_active', true)->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_MINUTES)))
            ->when($request->connection === 'today', fn($q) => $q->where('is_active', true)->where('last_seen_at', '>=', today()))
            ->when($request->connection === 'idle', fn($q) => $q->where('is_active', true)->where(fn($w) => $w->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays(self::IDLE_DAYS))))
            ->when($request->connection === 'never', fn($q) => $q->whereNull('last_seen_at'));
    }

    private function schools()
    {
        return Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->orderBy('name')->get(['id', 'name', 'type', 'is_active']);
    }

    private function formData(AttendanceDevice $device): array
    {
        $schools = $this->schools();

        return [
            'device'  => $device,
            'schools' => $schools,
            // Semua kelas sekolah tenant; di form difilter dengan JS sesuai sekolah terpilih
            'classes' => ClassRoom::whereIn('company_id', $schools->pluck('id'))
                ->orderByDesc('is_active')->orderBy('grade_level')->orderBy('name')
                ->get(['id', 'company_id', 'name', 'academic_year', 'is_active']),
        ];
    }

    private function validated(Request $request, ?AttendanceDevice $device = null): array
    {
        $schoolIds = Company::whereIn('type', SuperAdminSchoolClassController::SCHOOL_TYPES)->pluck('id')->all();

        $data = $request->validate([
            'company_id'        => ['required', Rule::in($schoolIds)],
            'class_id'          => [
                'nullable',
                Rule::exists('class_rooms', 'id')->where(fn($q) => $q->where('company_id', $request->company_id)),
            ],
            'name'              => ['required', 'string', 'max:100'],
            'device_identifier' => ['nullable', 'string', 'max:255'],
            'is_active'         => ['nullable', 'boolean'],
        ], [
            'company_id.in'   => 'Pilih sekolah atau pesantren yang valid.',
            'class_id.exists' => 'Kelas harus milik sekolah yang dipilih.',
        ], [
            'company_id'        => 'sekolah',
            'class_id'          => 'kelas',
            'name'              => 'nama kiosk',
            'device_identifier' => 'ID perangkat',
        ]);

        $data['class_id'] = $data['class_id'] ?? null;
        $data['device_identifier'] = $data['device_identifier'] ?? null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function newToken(): string
    {
        $length = (int) config('school.device_token_length', 64);

        do {
            $token = Str::random(max(32, $length));
        } while (AttendanceDevice::where('device_token', $token)->exists());

        return $token;
    }
}
