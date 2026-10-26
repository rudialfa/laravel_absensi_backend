<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leaves;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use App\Services\AttendancePhotoService;
use App\Services\AttendanceRuleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeAttendanceController extends Controller
{
    public function __construct(
        private AttendanceRuleService $rules,
        private AttendancePhotoService $photos,
    ) {}

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function me(): User
    {
        return auth()->user();
    }

    private function companyOrFail()
    {
        $company = $this->me()->company;
        if (!$company) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Company tidak ditemukan untuk user ini',
            ], 422));
        }
        return $company;
    }

    private function attendanceData(Attendance $a): array
    {
        return [
            'id'                    => (int) $a->id,
            'user_id'               => (int) $a->user_id,
            'company_id'            => (int) $a->company_id,
            'date'                  => $a->date?->toDateString(),
            'time_in'               => $a->time_in,
            'time_out'              => $a->time_out,
            'scheduled_in'          => $a->scheduled_in,
            'scheduled_out'         => $a->scheduled_out,
            'latlon_in'             => $a->latlon_in,
            'latlon_out'            => $a->latlon_out,
            'photo_in'              => $a->photo_in,
            'photo_out'             => $a->photo_out,
            'photo_in_url'          => $a->photo_in_url,
            'photo_out_url'         => $a->photo_out_url,
            'distance_in_m'         => $a->distance_in_m,
            'distance_out_m'        => $a->distance_out_m,
            'is_outside_radius_in'  => (bool) $a->is_outside_radius_in,
            'is_outside_radius_out' => (bool) $a->is_outside_radius_out,
            'status'                => $a->status,
            'late_minutes'          => (int) ($a->late_minutes ?? 0),
            'early_leave_minutes'   => (int) ($a->early_leave_minutes ?? 0),
            'overtime_minutes'      => (int) ($a->overtime_minutes ?? 0),
            'permission_id'         => $a->permission_id,
            'note'                  => $a->note,
            'marked_by'             => $a->marked_by,
        ];
    }

    private function permissionBrief(Permission $p): array
    {
        return [
            'id'         => $p->id,
            'type'       => $p->type,
            'type_label' => $p->type_label,
            'start_date' => $p->date_permission?->toDateString(),
            'end_date'   => $p->end_date?->toDateString(),
            'start_time' => $p->start_time,
            'end_time'   => $p->end_time,
            'reason'     => $p->reason,
        ];
    }

    /**
     * Absen yang masih terbuka (sudah masuk, belum pulang).
     * Mendukung shift malam: kalau hari ini belum ada, cek absen kemarin.
     */
    private function openAttendance(int $companyId, int $userId): ?Attendance
    {
        $today = Carbon::today()->toDateString();

        $att = Attendance::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereDate('date', $today)
            ->first();

        if ($att) {
            return $att;
        }

        $yesterday = Attendance::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereDate('date', Carbon::yesterday()->toDateString())
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->first();

        // Hanya dianggap shift malam kalau jam pulang terjadwal < jam masuk
        if (
            $yesterday && $yesterday->scheduled_in && $yesterday->scheduled_out
            && $yesterday->scheduled_out < $yesterday->scheduled_in
        ) {
            return $yesterday;
        }

        return null;
    }

    // ----------------------------------------------------------
    // REGISTER FACE  (DEPRECATED)
    // Absen sekarang pakai foto, tidak wajib register wajah lagi.
    // Endpoint dibiarkan supaya aplikasi versi lama tidak error.
    // ----------------------------------------------------------
    public function registerFace(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $request->validate([
            'face_embedding' => 'required|string|min:20',
        ]);

        $user = $this->me();
        $user->face_embedding = $request->face_embedding;
        $user->save();

        return response()->json([
            'status'     => true,
            'deprecated' => true,
            'message'    => 'Tersimpan. Catatan: absen sekarang memakai foto, register wajah tidak wajib lagi.',
        ]);
    }

    // ----------------------------------------------------------
    // CHECK-IN
    // POST /api/company/employee/attendances/check-in
    // Body JSON: { latitude, longitude, photo (base64 string), note? }
    // ----------------------------------------------------------
    public function checkIn(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo'     => 'required|string|min:100',
            'note'      => 'nullable|string|max:500',
        ], [
            'photo.required' => 'Foto absen wajib diambil.',
        ]);

        $user    = $this->me();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();
        $now     = Carbon::now();

        // Guard double check-in
        $existing = Attendance::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Sudah check-in hari ini pada pukul ' . $existing->time_in,
            ], 422);
        }

        // Izin yang sudah di-approve untuk hari ini
        $permissions = $this->rules->approvedPermissionsOn($user->id, $company->id, $today);
        $dinasLuar   = $this->rules->findDinasLuar($permissions);

        // Lokasi
        $distanceM = $this->rules->distanceMeters($company, (float) $request->latitude, (float) $request->longitude);
        $radiusM   = $this->rules->radiusMeters($company);
        $outside   = $distanceM > $radiusM;

        if ($outside && !$dinasLuar) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    'Anda berada di luar radius kantor (%s m, batas %s m).',
                    number_format($distanceM, 0, ',', '.'),
                    number_format($radiusM, 0, ',', '.')
                ),
                'hint' => 'Jika sedang dinas luar, ajukan izin "Dinas luar" dan tunggu persetujuan HR.',
                'data' => [
                    'distance_m' => $distanceM,
                    'radius_m'   => $radiusM,
                ],
            ], 422);
        }

        // Shift & keterlambatan (memperhitungkan izin yang sudah di-approve)
        $shift        = $this->rules->resolveShift($user, $today);
        $scheduledIn  = $this->rules->scheduledIn($shift, $company, $today);
        $scheduledOut = $this->rules->scheduledOut($shift, $company, $today);
        $late         = $this->rules->calcLate($shift, $company, $now, $today, $permissions);
        $usedPerm     = $late['permission'] ?? $dinasLuar;

        // Simpan foto paling akhir, setelah semua validasi lolos
        $photoPath = $this->photos->storeBase64($request->photo);

        try {
            $attendance = Attendance::firstOrNew([
                'company_id' => $company->id,
                'user_id'    => $user->id,
                'date'       => $today,
            ]);

            $note = $this->rules->buildNote($usedPerm);
            if ($request->filled('note')) {
                $note = trim(($note ? $note . ' | ' : '') . 'Catatan: ' . $request->note);
            }

            $attendance->fill([
                'marked_by'            => null,
                'shift_id'             => $shift?->id,
                'scheduled_in'         => $scheduledIn?->format('H:i:s'),
                'scheduled_out'        => $scheduledOut?->format('H:i:s'),
                'time_in'              => $now->format('H:i:s'),
                'latlon_in'            => $request->latitude . ',' . $request->longitude,
                'photo_in'             => $photoPath,
                'distance_in_m'        => $distanceM,
                'is_outside_radius_in' => $outside,
                'status'               => $late['late_minutes'] > 0 ? 'late' : 'on_time',
                'late_minutes'         => $late['late_minutes'],
                'face_verified'        => false,
                'permission_id'        => $usedPerm?->id,
                'note'                 => $note,
            ]);
            $attendance->save();
        } catch (\Throwable $e) {
            $this->photos->delete($photoPath);
            throw $e;
        }

        $msg = 'Check-in berhasil';
        if ($dinasLuar && $outside) $msg .= ' (dinas luar)';
        if ($late['late_minutes'] > 0) $msg .= " (terlambat {$late['late_minutes']} menit)";
        elseif ($late['permission']) $msg .= ' (sesuai izin, tidak dihitung terlambat)';

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceData($attendance),
        ]);
    }

    // ----------------------------------------------------------
    // CHECK-OUT
    // POST /api/company/employee/attendances/check-out
    // Body JSON: { latitude, longitude, photo (base64 string), note? }
    // ----------------------------------------------------------
    public function checkOut(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo'     => 'required|string|min:100',
            'note'      => 'nullable|string|max:500',
        ], [
            'photo.required' => 'Foto absen wajib diambil.',
        ]);

        $user    = $this->me();
        $company = $this->companyOrFail();
        $now     = Carbon::now();

        $attendance = $this->openAttendance($company->id, $user->id);

        if (!$attendance || !$attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Belum check-in hari ini',
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status'  => false,
                'message' => 'Sudah check-out hari ini pada pukul ' . $attendance->time_out,
            ], 422);
        }

        $workDate    = $attendance->date->toDateString();
        $permissions = $this->rules->approvedPermissionsOn($user->id, $company->id, $workDate);
        $dinasLuar   = $this->rules->findDinasLuar($permissions);

        $distanceM = $this->rules->distanceMeters($company, (float) $request->latitude, (float) $request->longitude);
        $radiusM   = $this->rules->radiusMeters($company);
        $outside   = $distanceM > $radiusM;

        if ($outside && !$dinasLuar) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    'Anda berada di luar radius kantor (%s m, batas %s m).',
                    number_format($distanceM, 0, ',', '.'),
                    number_format($radiusM, 0, ',', '.')
                ),
                'hint' => 'Jika sedang dinas luar, ajukan izin "Dinas luar" dan tunggu persetujuan HR.',
                'data' => [
                    'distance_m' => $distanceM,
                    'radius_m'   => $radiusM,
                ],
            ], 422);
        }

        $shift = $attendance->shift_id ? Shift::find($attendance->shift_id) : null;
        $early = $this->rules->calcEarlyLeave($shift, $company, $now, $workDate, $permissions);

        $photoPath = $this->photos->storeBase64($request->photo);

        try {
            $attendance->time_out              = $now->format('H:i:s');
            $attendance->latlon_out            = $request->latitude . ',' . $request->longitude;
            $attendance->photo_out             = $photoPath;
            $attendance->distance_out_m        = $distanceM;
            $attendance->is_outside_radius_out = $outside;
            $attendance->early_leave_minutes   = $early['early_leave_minutes'];

            // Kalau saat check-in belum ada izin yang terpakai, pakai izin pulang cepat
            if (!$attendance->permission_id && $early['permission']) {
                $attendance->permission_id = $early['permission']->id;
                $attendance->note = trim(
                    ($attendance->note ? $attendance->note . ' | ' : '')
                        . $this->rules->buildNote($early['permission'])
                );
            }

            if ($request->filled('note')) {
                $attendance->note = trim(
                    ($attendance->note ? $attendance->note . ' | ' : '') . 'Catatan pulang: ' . $request->note
                );
            }

            $attendance->save();
        } catch (\Throwable $e) {
            $this->photos->delete($photoPath);
            throw $e;
        }

        $msg = 'Check-out berhasil';
        if ($early['early_leave_minutes'] > 0) $msg .= " (pulang {$early['early_leave_minutes']} menit lebih awal)";
        elseif ($early['permission']) $msg .= ' (sesuai izin pulang cepat)';

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceData($attendance),
        ]);
    }

    // ----------------------------------------------------------
    // IS CHECKED IN
    // GET /api/company/employee/attendances/is-checkin
    // Sekarang juga mengirim izin aktif hari ini, supaya aplikasi bisa
    // menampilkan "Anda sedang dinas luar — absen bebas lokasi".
    // ----------------------------------------------------------
    public function isCheckedIn(): JsonResponse
    {
        $this->ensureEmployee();

        $user    = $this->me();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();

        $attendance  = $this->openAttendance($company->id, $user->id);
        $permissions = $this->rules->approvedPermissionsOn($user->id, $company->id, $today);

        $pending = Permission::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->whereNull('is_approved')
            ->activeOn($today)
            ->get();

        return response()->json([
            'status'      => true,
            'message'     => 'Status absensi hari ini',
            'checked_in'  => (bool) ($attendance && $attendance->time_in),
            'checked_out' => (bool) ($attendance && $attendance->time_out),
            'is_dinas_luar'            => (bool) $this->rules->findDinasLuar($permissions),
            'approved_permissions'     => $permissions->map(fn($p) => $this->permissionBrief($p))->values(),
            'pending_permissions'      => $pending->map(fn($p) => $this->permissionBrief($p))->values(),
            'data'        => $attendance ? $this->attendanceData($attendance) : null,
        ]);
    }

    // ----------------------------------------------------------
    // HISTORY
    // GET /api/company/employee/attendances/history
    // ----------------------------------------------------------
    public function history(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $user    = $this->me();
        $company = $this->companyOrFail();

        $limit = max(1, min((int) $request->query('limit', 30), 100));

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with(['shift', 'permission'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $data = $attendances->map(function ($a) {
            return array_merge($this->attendanceData($a), [
                'shift' => $a->shift ? [
                    'id'         => $a->shift->id,
                    'name'       => $a->shift->name,
                    'start_time' => $a->shift->start_time,
                    'end_time'   => $a->shift->end_time,
                ] : null,
                'permission' => $a->permission ? $this->permissionBrief($a->permission) : null,
            ]);
        })->values();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat absensi berhasil diambil',
            'data'    => $data,
        ]);
    }

    // ----------------------------------------------------------
    // SUMMARY STATISTIK BULANAN
    // GET /api/company/employee/stats/summary?month=&year=
    // ----------------------------------------------------------
    public function summary(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $user      = $this->me();
        $company   = $this->companyOrFail();
        $userId    = $user->id;
        $companyId = $company->id;

        $month = (int) $request->get('month', Carbon::now()->month);
        $year  = (int) $request->get('year', Carbon::now()->year);

        if ($month < 1 || $month > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Bulan tidak valid (1-12).',
            ], 422);
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $attendances = Attendance::where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'status', 'is_outside_radius_in']);

        $hadir     = $attendances->whereIn('status', ['on_time', 'overtime', 'guest'])->count();
        $terlambat = $attendances->where('status', 'late')->count();
        $alpha     = $attendances->where('status', 'absent')->count();
        $dinasLuar = $attendances->where('is_outside_radius_in', true)->count();

        $izin = Permission::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->where('is_approved', true)
            ->count();

        $cuti = Leaves::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->count();

        $bulanLabel = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan statistik kehadiran',
            'data'    => [
                'period' => [
                    'month' => $month,
                    'year'  => $year,
                    'label' => ($bulanLabel[$month] ?? $month) . ' ' . $year,
                ],
                'hadir'      => ['count' => $hadir,     'label' => 'Hari Hadir'],
                'terlambat'  => ['count' => $terlambat, 'label' => 'Terlambat'],
                'izin'       => ['count' => $izin,      'label' => 'Izin'],
                'cuti'       => ['count' => $cuti,      'label' => 'Cuti'],
                'alpha'      => ['count' => $alpha,     'label' => 'Alpha'],
                'dinas_luar' => ['count' => $dinasLuar, 'label' => 'Absen Dinas Luar'],
            ],
        ]);
    }
}
