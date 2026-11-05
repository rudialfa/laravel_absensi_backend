<?php

namespace App\Http\Controllers\Web\SuperAdmin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Models\ShiftGroupAssignment;
use App\Models\User;
use App\Models\UserShiftOverride;
use App\Services\AttendanceRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monitoring Shift & Jadwal karyawan (semua tenant perusahaan).
 * Tab: roster (shift per karyawan pada 1 tanggal) · shifts · groups · assignments · overrides · schedules
 * Pengaturan shift tetap wewenang HR. Superadmin memantau & menghapus data yang salah.
 */
class SuperAdminShiftController extends Controller
{
    public const TABS = [
        'roster'      => 'Shift Hari Ini',
        'shifts'      => 'Daftar Shift',
        'groups'      => 'Grup Shift',
        'assignments' => 'Penugasan Grup',
        'overrides'   => 'Shift Khusus',
        'schedules'   => 'Jadwal & Agenda',
    ];

    public function __construct(private AttendanceRuleService $rules) {}

    // =========================================================
    // INDEX (?tab=...)
    // =========================================================
    public function index(Request $request)
    {
        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'shifts';
        $companyId = $request->input('company_id');

        $data = match ($tab) {
            'roster'      => $this->roster($request),
            'groups'      => $this->groups($request),
            'assignments' => $this->assignments($request),
            'overrides'   => $this->overrides($request),
            'schedules'   => $this->schedules($request),
            default       => $this->shifts($request),
        };

        $counts = [
            'shifts'      => Shift::when($companyId, fn($q) => $q->where('company_id', $companyId))->count(),
            'groups'      => ShiftGroup::when($companyId, fn($q) => $q->where('company_id', $companyId))->count(),
            'assignments' => ShiftGroupAssignment::when($companyId, fn($q) => $q->where('company_id', $companyId))->count(),
            'overrides'   => UserShiftOverride::when($companyId, fn($q) => $q->where('company_id', $companyId))->count(),
            'schedules'   => Schedule::when($companyId, fn($q) => $q->where('company_id', $companyId))->count(),
        ];

        return view('pages.superadmin.monitoring.shifts.index', array_merge($data, [
            'tab'          => $tab,
            'tabs'         => self::TABS,
            'counts'       => $counts,
            'companies'    => Company::where('type', 'company')->orderBy('name')->get(['id', 'name']),
            'companyNames' => Company::pluck('name', 'id'),
        ]));
    }

    // ---------------------------------------------------------
    // TAB: Shift per karyawan pada 1 tanggal (pakai aturan yang sama dengan absen)
    // ---------------------------------------------------------
    private function roster(Request $request): array
    {
        $date    = $request->filled('date') ? Carbon::parse($request->date) : today();
        $company = $request->filled('company_id') ? Company::find($request->company_id) : null;
        $rows    = collect();

        if ($company) {
            $employees = User::where('company_id', $company->id)
                ->where('role', 'employee')
                ->where('is_active', true)
                ->orderBy('department')->orderBy('name')
                ->get(['id', 'name', 'department', 'position', 'company_id']);

            $attendances = Attendance::where('company_id', $company->id)
                ->whereDate('date', $date->toDateString())
                ->get(['id', 'user_id', 'time_in', 'time_out', 'status'])
                ->keyBy('user_id');

            $rows = $employees->map(function (User $u) use ($date, $attendances, $company) {
                $shift = $this->rules->resolveShift($u, $date->toDateString());

                return (object) [
                    'user'       => $u,
                    'shift'      => $shift,
                    'start'      => $shift ? substr($shift->start_time, 0, 5) : substr((string) $company->time_in, 0, 5),
                    'end'        => $shift ? substr($shift->end_time, 0, 5) : substr((string) $company->time_out, 0, 5),
                    'attendance' => $attendances[$u->id] ?? null,
                ];
            });
        }

        return [
            'rosterDate'    => $date,
            'rosterCompany' => $company,
            'rows'          => $rows,
            'shiftSummary'  => $rows->groupBy(fn($r) => $r->shift?->name ?? 'Jam default perusahaan')->map->count(),
        ];
    }

    // ---------------------------------------------------------
    // TAB: Shift
    // ---------------------------------------------------------
    private function shifts(Request $request): array
    {
        $items = Shift::query()
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'))
            // Model Shift tidak punya relasi hasMany, jadi hitung lewat subquery
            ->select('shifts.*')
            ->selectSub(DB::table('attendances')->selectRaw('count(*)')->whereColumn('attendances.shift_id', 'shifts.id'), 'attendances_count')
            ->selectSub(DB::table('shift_group_assignments')->selectRaw('count(*)')->whereColumn('shift_group_assignments.shift_id', 'shifts.id')->whereNull('deleted_at'), 'assignments_count')
            ->selectSub(DB::table('user_shift_overrides')->selectRaw('count(*)')->whereColumn('user_shift_overrides.shift_id', 'shifts.id')->whereNull('deleted_at'), 'overrides_count')
            ->orderBy('company_id')->orderByDesc('is_default')->orderBy('start_time')
            ->paginate(25)->withQueryString();

        return ['items' => $items];
    }

    // ---------------------------------------------------------
    // TAB: Grup
    // ---------------------------------------------------------
    private function groups(Request $request): array
    {
        $items = ShiftGroup::query()
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'))
            ->withCount('users')
            ->with(['assignments' => fn($q) => $q->with('shift:id,name,start_time,end_time')->orderByDesc('start_date')])
            ->orderBy('company_id')->orderBy('name')
            ->paginate(25)->withQueryString();

        return ['items' => $items];
    }

    // ---------------------------------------------------------
    // TAB: Penugasan grup → shift
    // ---------------------------------------------------------
    private function assignments(Request $request): array
    {
        $items = ShiftGroupAssignment::query()
            ->with(['group:id,name', 'shift:id,name,start_time,end_time', 'creator:id,name'])
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->boolean('active'), fn($q) => $q->where('start_date', '<=', today())
                ->where(fn($w) => $w->whereNull('end_date')->orWhere('end_date', '>=', today())))
            ->orderByDesc('start_date')
            ->paginate(25)->withQueryString();

        return ['items' => $items];
    }

    // ---------------------------------------------------------
    // TAB: Shift khusus per karyawan
    // ---------------------------------------------------------
    private function overrides(Request $request): array
    {
        $items = UserShiftOverride::query()
            ->with(['user:id,name,department', 'shift:id,name,start_time,end_time', 'creator:id,name'])
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->whereHas('user', fn($u) => $u->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%')))
            ->orderByDesc('start_date')
            ->paginate(25)->withQueryString();

        return ['items' => $items];
    }

    // ---------------------------------------------------------
    // TAB: Jadwal / agenda
    // ---------------------------------------------------------
    private function schedules(Request $request): array
    {
        $items = Schedule::query()
            ->with(['user:id,name', 'creator:id,name'])
            ->withCount('participants')
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->from, fn($q, $d) => $q->where('start_datetime', '>=', Carbon::parse($d)->startOfDay()))
            ->when($request->to, fn($q, $d) => $q->where('start_datetime', '<=', Carbon::parse($d)->endOfDay()))
            ->when($request->search, fn($q, $s) => $q->where('title', 'like', '%' . addcslashes($s, '%_\\') . '%'))
            ->orderByDesc('start_datetime')
            ->paginate(25)->withQueryString();

        return [
            'items'          => $items,
            'scheduleTypes'  => Schedule::TYPE_LABELS,
            'scheduleStatus' => Schedule::STATUS_LABELS,
        ];
    }

    // =========================================================
    // SHOW (/{type}/{id})
    // =========================================================
    public function show(string $type, $id)
    {
        return match ($type) {
            'shifts'    => $this->showShift($id),
            'groups'    => $this->showGroup($id),
            'schedules' => $this->showSchedule($id),
            default     => abort(404),
        };
    }

    private function showShift($id)
    {
        $shift = Shift::findOrFail($id);

        return view('pages.superadmin.monitoring.shifts.show-shift', [
            'shift'       => $shift,
            'company'     => Company::find($shift->company_id),
            'usage'       => $this->shiftUsage($shift),
            'assignments' => ShiftGroupAssignment::with('group:id,name')->where('shift_id', $shift->id)->orderByDesc('start_date')->limit(20)->get(),
            'overrides'   => UserShiftOverride::with('user:id,name')->where('shift_id', $shift->id)->orderByDesc('start_date')->limit(20)->get(),
            'recentAtt'   => Attendance::with('user:id,name')->where('shift_id', $shift->id)->orderByDesc('date')->limit(10)->get(['id', 'user_id', 'date', 'time_in', 'time_out', 'status']),
        ]);
    }

    private function showGroup($id)
    {
        $group = ShiftGroup::with([
            'users' => fn($q) => $q->select('users.id', 'users.name', 'users.department', 'users.is_active')->orderBy('users.name'),
            'assignments' => fn($q) => $q->with(['shift:id,name,start_time,end_time', 'creator:id,name'])->orderByDesc('start_date'),
        ])->findOrFail($id);

        return view('pages.superadmin.monitoring.shifts.show-group', [
            'group'   => $group,
            'company' => Company::find($group->company_id),
        ]);
    }

    private function showSchedule($id)
    {
        $schedule = Schedule::with(['user:id,name,department', 'creator:id,name', 'participants.user:id,name,department'])->findOrFail($id);

        return view('pages.superadmin.monitoring.shifts.show-schedule', [
            'schedule'       => $schedule,
            'company'        => Company::find($schedule->company_id),
            'scheduleTypes'  => Schedule::TYPE_LABELS,
            'scheduleStatus' => Schedule::STATUS_LABELS,
        ]);
    }

    // =========================================================
    // DESTROY (/{type}/{id})
    // =========================================================
    public function destroy(Request $request, string $type, $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'alasan']);

        [$model, $label, $guardError] = match ($type) {
            'shifts'      => $this->prepareShiftDelete($id),
            'groups'      => $this->prepareGroupDelete($id),
            'assignments' => [ShiftGroupAssignment::findOrFail($id), 'Penugasan grup', null],
            'overrides'   => [UserShiftOverride::findOrFail($id), 'Shift khusus', null],
            'schedules'   => [Schedule::findOrFail($id), 'Jadwal', null],
            default       => abort(404),
        };

        if ($guardError) {
            return back()->with('error', $guardError);
        }

        $snapshot = $model->getAttributes();

        DB::transaction(fn() => $model->delete()); // grup/penugasan/shift khusus/jadwal = soft delete

        AuditLog::record("delete_{$type}", null, "{$label} #{$model->id} (tenant #{$model->company_id}) dihapus. Alasan: {$data['reason']}", array_merge($snapshot, ['reason' => $data['reason']]));

        $backTab = in_array($type, array_keys(self::TABS), true) ? $type : 'shifts';

        return redirect()
            ->route('superadmin.monitoring.shifts.index', ['tab' => $backTab, 'company_id' => $model->company_id])
            ->with('success', "{$label} dihapus.");
    }

    private function prepareShiftDelete($id): array
    {
        $shift = Shift::findOrFail($id);
        $usage = $this->shiftUsage($shift);

        // Shift dihapus permanen & FK assignment/override = cascade → hanya boleh kalau tidak dipakai
        $error = ($usage['attendances'] + $usage['assignments'] + $usage['overrides']) > 0
            ? "Shift {$shift->name} masih dipakai ({$usage['attendances']} absen, {$usage['assignments']} penugasan, {$usage['overrides']} shift khusus). Minta HR menggantinya dulu."
            : null;

        return [$shift, "Shift {$shift->name}", $error];
    }

    private function prepareGroupDelete($id): array
    {
        $group = ShiftGroup::findOrFail($id);

        $activeAssignment = ShiftGroupAssignment::where('shift_group_id', $group->id)
            ->where('start_date', '<=', today())
            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', today()))
            ->exists();

        $error = $activeAssignment
            ? "Grup {$group->name} masih punya penugasan shift yang berjalan. Hapus/akhiri penugasannya dulu."
            : null;

        return [$group, "Grup {$group->name}", $error];
    }

    /** Berapa kali shift dipakai */
    private function shiftUsage(Shift $shift): array
    {
        return [
            'attendances' => Attendance::where('shift_id', $shift->id)->count(),
            'assignments' => ShiftGroupAssignment::where('shift_id', $shift->id)->count(),
            'overrides'   => UserShiftOverride::where('shift_id', $shift->id)->count(),
        ];
    }
}
