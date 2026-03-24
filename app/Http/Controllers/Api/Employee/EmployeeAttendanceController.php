<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leaves;
use App\Models\Permission;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeAttendanceController extends Controller
{
    // // ==========================
    // // Helper: Pastikan role employee
    // // ==========================
    // private function ensureEmployee()
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'employee') {
    //         abort(response()->json([
    //             'status' => false,
    //             'message' => 'Akses ditolak (khusus employee)'
    //         ], 403));
    //     }
    // }

    // // =========================================
    // // EMPLOYEE: REGISTER / UPDATE FACE EMBEDDING (optional)
    // // =========================================
    // public function registerFace(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $request->validate([
    //         'face_embedding' => 'required|string|min:20',
    //     ]);

    //     /** @var User $user */
    //     $user = auth()->user();

    //     $user->face_embedding = $request->face_embedding;
    //     $user->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Face embedding berhasil disimpan',
    //         'data' => [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'face_embedding' => $user->face_embedding,
    //         ]
    //     ], 200);
    // }

    // // ==========================
    // // EMPLOYEE: CHECK-IN (SELF)
    // // ==========================
    // public function checkIn(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $request->validate([
    //         'latitude' => 'required',
    //         'longitude' => 'required',
    //     ]);

    //     $today = Carbon::today();

    //     // Cegah double check-in (SELF = marked_by null)
    //     $exists = Attendance::where('user_id', auth()->id())
    //         ->whereDate('date', $today)
    //         ->whereNull('marked_by')
    //         ->first();

    //     if ($exists) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Sudah check-in hari ini'
    //         ], 422);
    //     }

    //     $attendance = new Attendance();
    //     $attendance->company_id = auth()->user()->company_id; // ✅
    //     $attendance->user_id = auth()->id();
    //     $attendance->marked_by = null; // SELF
    //     $attendance->date = $today;
    //     $attendance->time_in = Carbon::now()->format('H:i:s');
    //     $attendance->latlon_in = $request->latitude . ',' . $request->longitude;
    //     $attendance->status = 'on_time';
    //     $attendance->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Check-in berhasil',
    //         'attendance' => $attendance
    //     ], 200);
    // }

    // // ==========================
    // // EMPLOYEE: CHECK-OUT (SELF)
    // // ==========================
    // public function checkOut(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $request->validate([
    //         'latitude' => 'required',
    //         'longitude' => 'required',
    //     ]);

    //     $today = Carbon::today();

    //     $attendance = Attendance::where('user_id', auth()->id())
    //         ->whereDate('date', $today)
    //         ->whereNull('marked_by')
    //         ->first();

    //     if (!$attendance) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Belum check-in'
    //         ], 422);
    //     }

    //     if ($attendance->time_out) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Sudah check-out hari ini'
    //         ], 422);
    //     }

    //     $attendance->time_out = Carbon::now()->format('H:i:s');
    //     $attendance->latlon_out = $request->latitude . ',' . $request->longitude;
    //     $attendance->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Check-out berhasil',
    //         'attendance' => $attendance
    //     ], 200);
    // }

    // // ==========================
    // // EMPLOYEE: STATUS HARI INI
    // // ==========================
    // public function isCheckedIn()
    // {
    //     $this->ensureEmployee();

    //     $today = Carbon::today();

    //     $attendance = Attendance::where('user_id', auth()->id())
    //         ->whereDate('date', $today)
    //         ->whereNull('marked_by')
    //         ->first();

    //     return response()->json([
    //         'status' => true,
    //         'checked_in' => $attendance ? true : false,
    //         'checked_out' => $attendance && $attendance->time_out ? true : false,
    //         'attendance' => $attendance
    //     ], 200);
    // }

    // // ==========================
    // // EMPLOYEE: HISTORY (SELF)
    // // ==========================
    // public function history(Request $request)
    // {
    //     $this->ensureEmployee();

    //     $limit = (int) ($request->query('limit', 30));
    //     if ($limit <= 0) $limit = 30;
    //     if ($limit > 100) $limit = 100;

    //     $data = Attendance::where('user_id', auth()->id())
    //         ->orderBy('date', 'desc')
    //         ->orderBy('id', 'desc')
    //         ->limit($limit)
    //         ->get();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Success',
    //         'data' => $data
    //     ], 200);
    // }

    // // summary
    // private function user(Request $request)
    // {
    //     return $request->user();
    // }

    // private function userId(Request $request): int
    // {
    //     return (int) $this->user($request)->id;
    // }

    // private function companyId(Request $request): int
    // {
    //     return (int) $this->user($request)->company_id;
    // }

    // /**
    //  * GET /api/company/employee/stats/summary
    //  *
    //  * Query params (opsional):
    //  *   month = bulan (1-12), default bulan ini
    //  *   year  = tahun (YYYY), default tahun ini
    //  *
    //  * Response:
    //  * {
    //  *   "success": true,
    //  *   "data": {
    //  *     "period": { "month": 3, "year": 2026, "label": "Maret 2026" },
    //  *     "hadir":    { "count": 18, "label": "Hari Hadir" },
    //  *     "terlambat":{ "count": 3,  "label": "Terlambat" },
    //  *     "izin":     { "count": 2,  "label": "Izin" },
    //  *     "cuti":     { "count": 1,  "label": "Cuti" },
    //  *     "alpha":    { "count": 0,  "label": "Alpha" }
    //  *   }
    //  * }
    //  */
    // public function summary(Request $request)
    // {
    //     $userId    = $this->userId($request);
    //     $companyId = $this->companyId($request);

    //     $month = (int) $request->get('month', Carbon::now()->month);
    //     $year  = (int) $request->get('year',  Carbon::now()->year);

    //     // Validasi sederhana
    //     if ($month < 1 || $month > 12) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Bulan tidak valid (1-12).',
    //         ], 422);
    //     }

    //     $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
    //     $end   = $start->copy()->endOfMonth();

    //     // ── Attendances bulan ini milik user ──
    //     $attendances = Attendance::where('user_id', $userId)
    //         ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
    //         ->get(['date', 'status']);

    //     // Hitung hadir = on_time + overtime + guest (yang masuk kerja)
    //     $hadir     = $attendances->whereIn('status', ['on_time', 'overtime', 'guest'])->count();
    //     $terlambat = $attendances->where('status', 'late')->count();
    //     $alpha     = $attendances->where('status', 'absent')->count();

    //     // ── Izin (permissions) bulan ini yang approved ──
    //     $izin = Permission::where('user_id', $userId)
    //         ->where('company_id', $companyId)
    //         ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
    //         ->where('is_approved', true)
    //         ->count();

    //     // ── Cuti (leaves) yang approved & overlap bulan ini ──
    //     $cuti = Leaves::where('user_id', $userId)
    //         ->where('company_id', $companyId)
    //         ->where('status', 'approved')
    //         ->where('start_date', '<=', $end->toDateString())
    //         ->where('end_date', '>=', $start->toDateString())
    //         ->count();

    //     // Label bulan Indonesia
    //     $bulanLabel = [
    //         1 => 'Januari',
    //         2 => 'Februari',
    //         3 => 'Maret',
    //         4 => 'April',
    //         5 => 'Mei',
    //         6 => 'Juni',
    //         7 => 'Juli',
    //         8 => 'Agustus',
    //         9 => 'September',
    //         10 => 'Oktober',
    //         11 => 'November',
    //         12 => 'Desember',
    //     ];

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Ringkasan statistik kehadiran',
    //         'data' => [
    //             'period' => [
    //                 'month' => $month,
    //                 'year'  => $year,
    //                 'label' => ($bulanLabel[$month] ?? $month) . ' ' . $year,
    //             ],
    //             'hadir'     => ['count' => $hadir,     'label' => 'Hari Hadir'],
    //             'terlambat' => ['count' => $terlambat,  'label' => 'Terlambat'],
    //             'izin'      => ['count' => $izin,       'label' => 'Izin'],
    //             'cuti'      => ['count' => $cuti,       'label' => 'Cuti'],
    //             'alpha'     => ['count' => $alpha,      'label' => 'Alpha'],
    //         ],
    //     ]);
    // }


    // kode 2
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

    private function haversineKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // ----------------------------------------------------------
    // SHIFT RESOLVER
    // Prioritas: override → group → default → null (company fallback)
    // ----------------------------------------------------------

    private function resolveShift(User $user, string $date): ?Shift
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

        if ($override) return Shift::find($override->shift_id);

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

            if ($assignment) return Shift::find($assignment->shift_id);
        }

        return Shift::where('company_id', $user->company_id)
            ->where('is_default', true)
            ->first();
    }

    private function getScheduledIn(?Shift $shift, $company, string $date): ?Carbon
    {
        $time = $shift ? $shift->start_time : $company?->time_in;
        return $time ? Carbon::parse($date . ' ' . $time) : null;
    }

    private function getScheduledOut(?Shift $shift, $company, string $date): ?Carbon
    {
        $time = $shift ? $shift->end_time : $company?->time_out;
        return $time ? Carbon::parse($date . ' ' . $time) : null;
    }

    private function calcLateMinutes(
        ?Shift $shift,
        $company,
        Carbon $actualIn,
        string $date
    ): int {
        $scheduledIn = $this->getScheduledIn($shift, $company, $date);
        if (!$scheduledIn) return 0;
        $grace    = $shift?->grace_period_minutes ?? 0;
        $deadline = $scheduledIn->copy()->addMinutes($grace);
        if ($actualIn->lte($deadline)) return 0;
        return (int) $actualIn->diffInMinutes($scheduledIn);
    }

    private function calcEarlyLeaveMinutes(
        ?Shift $shift,
        $company,
        Carbon $actualOut,
        string $date
    ): int {
        $scheduledOut = $this->getScheduledOut($shift, $company, $date);
        if (!$scheduledOut) return 0;
        if ($actualOut->gte($scheduledOut)) return 0;
        return (int) $scheduledOut->diffInMinutes($actualOut);
    }

    private function attendanceData(Attendance $a): array
    {
        return [
            'id'                  => (int) $a->id,
            'user_id'             => (int) $a->user_id,
            'company_id'          => (int) $a->company_id,
            'date'                => $a->date,
            'time_in'             => $a->time_in,
            'time_out'            => $a->time_out,
            'scheduled_in'        => $a->scheduled_in,
            'scheduled_out'       => $a->scheduled_out,
            'latlon_in'           => $a->latlon_in,
            'latlon_out'          => $a->latlon_out,
            'status'              => $a->status,
            'late_minutes'        => (int) ($a->late_minutes ?? 0),
            'early_leave_minutes' => (int) ($a->early_leave_minutes ?? 0),
            'overtime_minutes'    => (int) ($a->overtime_minutes ?? 0),
            'face_verified'       => (bool) ($a->face_verified ?? false),
            'marked_by'           => $a->marked_by,
        ];
    }

    // ----------------------------------------------------------
    // REGISTER FACE
    // POST /api/company/employee/attendances/register-face
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
            'status'  => true,
            'message' => 'Face embedding berhasil disimpan',
            'data'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'face_embedding' => $user->face_embedding,
            ],
        ]);
    }

    // ----------------------------------------------------------
    // CHECK-IN
    // POST /api/company/employee/attendances/check-in
    //
    // ✅ M2  — field time_in konsisten
    // ✅ M3  — geofence radius, guard double check-in
    // ✅ M4  — blokir jika belum register face
    // ✅ M5  — query & save pakai company_id
    // ✅ M11 — resolve shift, hitung late_minutes
    // ✅ M17 — field time_in, company_id selalu ada
    // ----------------------------------------------------------
    public function checkIn(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user    = $this->me();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();
        $now     = Carbon::now();

        // [M4] Cek face_embedding — wajib register face dulu
        if (empty($user->face_embedding)) {
            return response()->json([
                'status'       => false,
                'face_warning' => true,
                'message'      => 'Anda belum melakukan registrasi wajah.',
                'hint'         => 'Silahkan lakukan register face terlebih dahulu melalui menu "Daftar Wajah" di halaman absensi, kemudian coba check-in kembali.',
            ], 422);
        }

        // [M3] Guard double check-in — query by company_id + user_id + date
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

        // [M3] Validasi geofence radius
        $distanceKm = $this->haversineKm(
            (float) $company->latitude,
            (float) $company->longitude,
            (float) $request->latitude,
            (float) $request->longitude
        );

        if ($distanceKm > (float) $company->radius_km) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    'Anda berada di luar radius perusahaan (%s m dari batas %s m)',
                    number_format($distanceKm * 1000, 0),
                    number_format((float) $company->radius_km * 1000, 0)
                ),
                'data' => [
                    'distance_km' => round($distanceKm, 3),
                    'radius_km'   => (float) $company->radius_km,
                ],
            ], 422);
        }

        // [M11] Resolve shift aktif & hitung keterlambatan
        $shift        = $this->resolveShift($user, $today);
        $scheduledIn  = $this->getScheduledIn($shift, $company, $today);
        $scheduledOut = $this->getScheduledOut($shift, $company, $today);
        $lateMinutes  = $this->calcLateMinutes($shift, $company, $now, $today);

        // [M5] firstOrNew pakai company_id
        $attendance = Attendance::firstOrNew([
            'company_id' => $company->id,
            'user_id'    => $user->id,
            'date'       => $today,
        ]);

        // [M17] Field time_in, company_id selalu di-set
        $attendance->company_id    = $company->id;
        $attendance->user_id       = $user->id;
        $attendance->date          = $today;
        $attendance->marked_by     = null;
        $attendance->shift_id      = $shift?->id;
        $attendance->scheduled_in  = $scheduledIn?->format('H:i:s');
        $attendance->scheduled_out = $scheduledOut?->format('H:i:s');
        $attendance->time_in       = $now->format('H:i:s');
        $attendance->latlon_in     = $request->latitude . ',' . $request->longitude;
        $attendance->status        = $lateMinutes > 0 ? 'late' : 'on_time';
        $attendance->late_minutes  = $lateMinutes;
        $attendance->face_verified = false;
        $attendance->save();

        $msg = 'Check-in berhasil';
        if ($lateMinutes > 0) $msg .= " (terlambat {$lateMinutes} menit)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceData($attendance),
        ]);
    }

    // ----------------------------------------------------------
    // CHECK-OUT
    // POST /api/company/employee/attendances/check-out
    //
    // ✅ M3  — guard wajib checkin dulu, guard double checkout
    // ✅ M5  — query pakai company_id
    // ✅ M11 — hitung early_leave_minutes dari shift
    // ✅ M17 — field time_out konsisten
    // ----------------------------------------------------------
    public function checkOut(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user    = $this->me();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();
        $now     = Carbon::now();

        // [M5] Query pakai company_id
        $attendance = Attendance::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        // [M3] Harus checkin dulu
        if (!$attendance || !$attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Belum check-in hari ini',
            ], 422);
        }

        // [M3] Guard double checkout
        if ($attendance->time_out) {
            return response()->json([
                'status'  => false,
                'message' => 'Sudah check-out hari ini pada pukul ' . $attendance->time_out,
            ], 422);
        }

        // [M11] Hitung pulang lebih awal dari shift
        $shift             = $attendance->shift_id ? Shift::find($attendance->shift_id) : null;
        $earlyLeaveMinutes = $this->calcEarlyLeaveMinutes($shift, $company, $now, $today);

        $attendance->time_out            = $now->format('H:i:s');
        $attendance->latlon_out          = $request->latitude . ',' . $request->longitude;
        $attendance->early_leave_minutes = $earlyLeaveMinutes;
        $attendance->save();

        $msg = 'Check-out berhasil';
        if ($earlyLeaveMinutes > 0) $msg .= " (pulang {$earlyLeaveMinutes} menit lebih awal)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceData($attendance),
        ]);
    }

    // ----------------------------------------------------------
    // IS CHECKED IN
    // GET /api/company/employee/attendances/is-checkin
    //
    // ✅ M5 — query pakai company_id (bukan whereNull marked_by)
    // ----------------------------------------------------------
    public function isCheckedIn(): JsonResponse
    {
        $this->ensureEmployee();

        $user    = $this->me();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();

        $attendance = Attendance::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        return response()->json([
            'status'      => true,
            'message'     => 'Status absensi hari ini',
            'checked_in'  => $attendance && $attendance->time_in  ? true : false,
            'checked_out' => $attendance && $attendance->time_out ? true : false,
            'data'        => $attendance ? $this->attendanceData($attendance) : null,
        ]);
    }

    // ----------------------------------------------------------
    // HISTORY
    // GET /api/company/employee/attendances/history
    //
    // ✅ M5 — filter by company_id
    // ----------------------------------------------------------
    public function history(Request $request): JsonResponse
    {
        $this->ensureEmployee();

        $user    = $this->me();
        $company = $this->companyOrFail();

        $limit = (int) ($request->query('limit', 30));
        $limit = max(1, min($limit, 100));

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->with('shift')
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
        $year  = (int) $request->get('year',  Carbon::now()->year);

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
            ->get(['date', 'status']);

        $hadir     = $attendances->whereIn('status', ['on_time', 'overtime', 'guest'])->count();
        $terlambat = $attendances->where('status', 'late')->count();
        $alpha     = $attendances->where('status', 'absent')->count();

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
                'period'    => [
                    'month' => $month,
                    'year'  => $year,
                    'label' => ($bulanLabel[$month] ?? $month) . ' ' . $year,
                ],
                'hadir'     => ['count' => $hadir,     'label' => 'Hari Hadir'],
                'terlambat' => ['count' => $terlambat, 'label' => 'Terlambat'],
                'izin'      => ['count' => $izin,      'label' => 'Izin'],
                'cuti'      => ['count' => $cuti,      'label' => 'Cuti'],
                'alpha'     => ['count' => $alpha,     'label' => 'Alpha'],
            ],
        ]);
    }
}
