<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semua aturan absen karyawan dikumpulkan di sini supaya dipakai sama persis oleh:
 *  - EmployeeAttendanceController (check-in / check-out)
 *  - HrCompanyPermissionController (hitung ulang saat izin di-approve / reject)
 *
 * Aturan izin:
 *  - Izin hanya berpengaruh kalau SUDAH di-approve HR (is_approved = true).
 *  - datang_terlambat s/d 10:00, datang 10:00  → tidak telat.
 *  - datang_terlambat s/d 10:00, datang 11:00  → telat 60 menit (dihitung dari 10:00).
 *  - keluar_kantor yang dimulai sejak jam masuk diperlakukan sama seperti datang_terlambat.
 *  - pulang_cepat mulai 14:00 → pulang 14:00 tidak dihitung pulang cepat.
 *  - dinas_luar → radius tidak dicek, lokasi tetap dicatat.
 */
class AttendanceRuleService
{
    // ----------------------------------------------------------
    // SHIFT
    // Prioritas: override → group → default → null (pakai jam company)
    // ----------------------------------------------------------

    public function resolveShift(User $user, string $date): ?Shift
    {
        $override = DB::table('user_shift_overrides')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('start_date')
            ->first();

        if ($override) {
            return Shift::find($override->shift_id);
        }

        $groupIds = DB::table('shift_group_users')
            ->where('user_id', $user->id)
            ->where(function ($q) use ($date) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->pluck('shift_group_id');

        if ($groupIds->isNotEmpty()) {
            $assignment = DB::table('shift_group_assignments')
                ->whereIn('shift_group_id', $groupIds)
                ->where('company_id', $user->company_id)
                ->where('start_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
                })
                ->whereNull('deleted_at')
                ->orderByDesc('start_date')
                ->first();

            if ($assignment) {
                return Shift::find($assignment->shift_id);
            }
        }

        return Shift::where('company_id', $user->company_id)
            ->where('is_default', true)
            ->first();
    }

    public function scheduledIn(?Shift $shift, Company $company, string $date): ?Carbon
    {
        $time = $shift ? $shift->start_time : $company->time_in;

        return $time ? Carbon::parse($date . ' ' . $time) : null;
    }

    public function scheduledOut(?Shift $shift, Company $company, string $date): ?Carbon
    {
        $endTime = $shift ? $shift->end_time : $company->time_out;
        if (!$endTime) {
            return null;
        }

        $out = Carbon::parse($date . ' ' . $endTime);

        // Shift malam (misal 22:00 - 06:00): jam pulang jatuh di hari berikutnya
        $in = $this->scheduledIn($shift, $company, $date);
        if ($in && $out->lte($in)) {
            $out->addDay();
        }

        return $out;
    }

    // ----------------------------------------------------------
    // IZIN
    // ----------------------------------------------------------

    /** Semua izin yang SUDAH di-approve dan berlaku di tanggal tsb. */
    public function approvedPermissionsOn(int $userId, int $companyId, string $date): Collection
    {
        return Permission::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->approved()
            ->activeOn($date)
            ->orderByDesc('id')
            ->get();
    }

    public function findDinasLuar(Collection $permissions): ?Permission
    {
        return $permissions->firstWhere('type', Permission::TYPE_DINAS_LUAR);
    }

    /**
     * Jam masuk yang diizinkan. Mengembalikan [Carbon|null, Permission|null].
     */
    public function allowedIn(?Carbon $scheduledIn, Collection $permissions, string $date): array
    {
        if (!$scheduledIn) {
            return [null, null];
        }

        $best = null;
        $bestTime = $scheduledIn->copy();

        foreach ($permissions as $perm) {
            $candidate = null;

            if ($perm->type === Permission::TYPE_DATANG_TERLAMBAT && $perm->end_time) {
                $candidate = Carbon::parse($date . ' ' . $perm->end_time);
            }

            // Izin keluar yang dimulai saat/ sebelum jam masuk = datang terlambat
            if (
                $perm->type === Permission::TYPE_KELUAR_KANTOR
                && $perm->start_time && $perm->end_time
                && Carbon::parse($date . ' ' . $perm->start_time)->lte($scheduledIn)
            ) {
                $candidate = Carbon::parse($date . ' ' . $perm->end_time);
            }

            if ($candidate && $candidate->gt($bestTime)) {
                $bestTime = $candidate;
                $best = $perm;
            }
        }

        return [$bestTime, $best];
    }

    /**
     * Jam pulang yang diizinkan. Mengembalikan [Carbon|null, Permission|null].
     */
    public function allowedOut(?Carbon $scheduledOut, Collection $permissions, string $date): array
    {
        if (!$scheduledOut) {
            return [null, null];
        }

        $best = null;
        $bestTime = $scheduledOut->copy();

        foreach ($permissions as $perm) {
            $candidate = null;

            if ($perm->type === Permission::TYPE_PULANG_CEPAT && $perm->start_time) {
                $candidate = Carbon::parse($date . ' ' . $perm->start_time);
            }

            // Izin keluar yang berlangsung sampai jam pulang = pulang cepat
            if (
                $perm->type === Permission::TYPE_KELUAR_KANTOR
                && $perm->start_time && $perm->end_time
                && Carbon::parse($date . ' ' . $perm->end_time)->gte($scheduledOut)
            ) {
                $candidate = Carbon::parse($date . ' ' . $perm->start_time);
            }

            if ($candidate && $candidate->lt($bestTime)) {
                $bestTime = $candidate;
                $best = $perm;
            }
        }

        return [$bestTime, $best];
    }

    // ----------------------------------------------------------
    // HITUNG TELAT & PULANG CEPAT
    // ----------------------------------------------------------

    /**
     * @return array{late_minutes:int, permission:?Permission}
     */
    public function calcLate(
        ?Shift $shift,
        Company $company,
        Carbon $actualIn,
        string $date,
        Collection $permissions
    ): array {
        $scheduledIn = $this->scheduledIn($shift, $company, $date);
        if (!$scheduledIn) {
            return ['late_minutes' => 0, 'permission' => null];
        }

        [$allowedIn, $usedPermission] = $this->allowedIn($scheduledIn, $permissions, $date);

        // Grace period hanya berlaku untuk jadwal normal.
        // Kalau pakai izin, jam izin itu sendiri sudah jadi kelonggarannya.
        $deadline = $usedPermission
            ? $allowedIn->copy()
            : $allowedIn->copy()->addMinutes($shift?->grace_period_minutes ?? 0);

        if ($actualIn->lte($deadline)) {
            return ['late_minutes' => 0, 'permission' => $usedPermission];
        }

        // diffInMinutes($x, true) = selalu positif, aman untuk Carbon 2 & 3
        return [
            'late_minutes' => (int) $allowedIn->diffInMinutes($actualIn, true),
            'permission'   => $usedPermission,
        ];
    }

    /**
     * @return array{early_leave_minutes:int, permission:?Permission}
     */
    public function calcEarlyLeave(
        ?Shift $shift,
        Company $company,
        Carbon $actualOut,
        string $date,
        Collection $permissions
    ): array {
        $scheduledOut = $this->scheduledOut($shift, $company, $date);
        if (!$scheduledOut) {
            return ['early_leave_minutes' => 0, 'permission' => null];
        }

        [$allowedOut, $usedPermission] = $this->allowedOut($scheduledOut, $permissions, $date);

        if ($actualOut->gte($allowedOut)) {
            return ['early_leave_minutes' => 0, 'permission' => $usedPermission];
        }

        return [
            'early_leave_minutes' => (int) $allowedOut->diffInMinutes($actualOut, true),
            'permission'          => $usedPermission,
        ];
    }

    public function buildNote(?Permission $permission): ?string
    {
        if (!$permission) {
            return null;
        }

        $label = Permission::TYPE_LABELS[$permission->type] ?? 'Izin';

        $range = match (true) {
            $permission->start_time && $permission->end_time =>
            ' ' . substr($permission->start_time, 0, 5) . '–' . substr($permission->end_time, 0, 5),
            (bool) $permission->end_time   => ' s/d ' . substr($permission->end_time, 0, 5),
            (bool) $permission->start_time => ' mulai ' . substr($permission->start_time, 0, 5),
            default                        => '',
        };

        return $label . $range . ' — ' . $permission->reason;
    }

    // ----------------------------------------------------------
    // HITUNG ULANG
    // Dipanggil saat HR approve / reject izin SETELAH karyawan sudah absen.
    // ----------------------------------------------------------

    public function recalculate(int $userId, int $companyId, string $date): ?Attendance
    {
        $attendance = Attendance::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->first();

        // Absen manual oleh HR (alpa, izin seharian, dll) tidak diubah otomatis
        if (!$attendance || !$attendance->time_in || $attendance->marked_by) {
            return $attendance;
        }

        $user    = User::findOrFail($userId);
        $company = Company::findOrFail($companyId);
        $shift   = $attendance->shift_id ? Shift::find($attendance->shift_id) : $this->resolveShift($user, $date);
        $perms   = $this->approvedPermissionsOn($userId, $companyId, $date);

        $late = $this->calcLate($shift, $company, Carbon::parse($date . ' ' . $attendance->time_in), $date, $perms);

        $early = ['early_leave_minutes' => 0, 'permission' => null];
        if ($attendance->time_out) {
            $out = Carbon::parse($date . ' ' . $attendance->time_out);
            $scheduledIn = $this->scheduledIn($shift, $company, $date);
            if ($scheduledIn && $out->lt($scheduledIn)) {
                $out->addDay(); // pulang shift malam
            }
            $early = $this->calcEarlyLeave($shift, $company, $out, $date, $perms);
        }

        $dinasLuar = $this->findDinasLuar($perms);
        $used = $late['permission'] ?? $early['permission'] ?? $dinasLuar;

        $attendance->late_minutes        = $late['late_minutes'];
        $attendance->early_leave_minutes = $early['early_leave_minutes'];
        $attendance->status              = $late['late_minutes'] > 0 ? 'late' : 'on_time';
        $attendance->permission_id       = $used?->id;
        $attendance->note                = $this->buildNote($used);
        $attendance->save();

        return $attendance;
    }

    /**
     * Hitung ulang semua tanggal yang dicakup sebuah izin.
     */
    public function recalculateForPermission(Permission $permission): void
    {
        $start = Carbon::parse($permission->date_permission)->startOfDay();
        $end   = $permission->end_date
            ? Carbon::parse($permission->end_date)->startOfDay()
            : $start->copy();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $this->recalculate($permission->user_id, $permission->company_id, $day->toDateString());
        }
    }

    // ----------------------------------------------------------
    // LOKASI
    // ----------------------------------------------------------

    public function distanceMeters(Company $company, float $lat, float $lng): int
    {
        $R    = 6371000;
        $lat1 = (float) $company->latitude;
        $lng1 = (float) $company->longitude;
        $dLat = deg2rad($lat - $lat1);
        $dLng = deg2rad($lng - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return (int) round($R * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function radiusMeters(Company $company): int
    {
        return (int) round(((float) $company->radius_km) * 1000);
    }
}
