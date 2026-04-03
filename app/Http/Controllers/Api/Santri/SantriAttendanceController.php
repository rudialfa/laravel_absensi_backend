<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SantriAttendanceController extends Controller
{
    // // ============================================================
    // // PRIVATE HELPERS
    // // ============================================================

    // private function ensureSantri(): void
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'santri') {
    //         abort(response()->json([
    //             'status'  => false,
    //             'message' => 'Akses ditolak (khusus Santri)',
    //         ], 403));
    //     }
    // }

    // private function me(): User
    // {
    //     return auth()->user();
    // }

    // private function pesantrenOrFail()
    // {
    //     $company = $this->me()->company;
    //     if (!$company) {
    //         abort(response()->json([
    //             'status'  => false,
    //             'message' => 'Pesantren tidak ditemukan untuk santri ini',
    //         ], 422));
    //     }
    //     return $company;
    // }

    // private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    // {
    //     $R    = 6371;
    //     $dLat = deg2rad($lat2 - $lat1);
    //     $dLng = deg2rad($lng2 - $lng1);
    //     $a    = sin($dLat / 2) ** 2
    //         + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    //     return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    // }

    // private function attendanceData(Attendance $a): array
    // {
    //     return [
    //         'id'                  => (int) $a->id,
    //         'user_id'             => (int) $a->user_id,
    //         'company_id'          => (int) $a->company_id,
    //         'date'                => $a->date,
    //         'time_in'             => $a->time_in,
    //         'time_out'            => $a->time_out,
    //         'scheduled_in'        => $a->scheduled_in,
    //         'scheduled_out'       => $a->scheduled_out,
    //         'latlon_in'           => $a->latlon_in,
    //         'latlon_out'          => $a->latlon_out,
    //         'status'              => $a->status,
    //         'late_minutes'        => (int) ($a->late_minutes        ?? 0),
    //         'early_leave_minutes' => (int) ($a->early_leave_minutes ?? 0),
    //         'face_verified'       => (bool) ($a->face_verified      ?? false),
    //         'marked_by'           => $a->marked_by,
    //     ];
    // }

    // private function bulanLabel(int $month): string
    // {
    //     return [
    //         1  => 'Januari',
    //         2  => 'Februari',
    //         3  => 'Maret',
    //         4  => 'April',
    //         5  => 'Mei',
    //         6  => 'Juni',
    //         7  => 'Juli',
    //         8  => 'Agustus',
    //         9  => 'September',
    //         10 => 'Oktober',
    //         11 => 'November',
    //         12 => 'Desember',
    //     ][$month] ?? (string) $month;
    // }

    // // ============================================================
    // // REGISTER FACE
    // // POST /api/pesantren/santri/attendances/register-face
    // // Sejajar: EmployeeAttendanceController::registerFace()
    // // ============================================================
    // public function registerFace(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $request->validate([
    //         'face_embedding' => 'required|string|min:20',
    //     ]);

    //     $santri = $this->me();
    //     $santri->face_embedding = $request->face_embedding;
    //     $santri->save();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Face embedding berhasil disimpan',
    //         'data'    => [
    //             'id'             => $santri->id,
    //             'name'           => $santri->name,
    //             'face_embedding' => $santri->face_embedding,
    //         ],
    //     ]);
    // }

    // // ============================================================
    // // CHECK-IN
    // // POST /api/pesantren/santri/attendances/check-in
    // // Sejajar: EmployeeAttendanceController::checkIn()
    // // Pesantren tidak pakai shift system — pakai time_in company
    // // ============================================================
    // public function checkIn(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $request->validate([
    //         'latitude'  => 'required|numeric|between:-90,90',
    //         'longitude' => 'required|numeric|between:-180,180',
    //     ]);

    //     $santri  = $this->me();
    //     $company = $this->pesantrenOrFail();
    //     $today   = Carbon::today()->toDateString();
    //     $now     = Carbon::now();

    //     // Wajib register face dulu
    //     if (empty($santri->face_embedding)) {
    //         return response()->json([
    //             'status'       => false,
    //             'face_warning' => true,
    //             'message'      => 'Anda belum melakukan registrasi wajah.',
    //             'hint'         => 'Silahkan lakukan register face terlebih dahulu melalui menu "Daftar Wajah".',
    //         ], 422);
    //     }

    //     // Guard double check-in
    //     $existing = Attendance::where('company_id', $company->id)
    //         ->where('user_id', $santri->id)
    //         ->whereDate('date', $today)
    //         ->first();

    //     if ($existing?->time_in) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Sudah check-in hari ini pada pukul ' . $existing->time_in,
    //         ], 422);
    //     }

    //     // Validasi geofence radius
    //     $distanceKm = $this->haversineKm(
    //         (float) $company->latitude,
    //         (float) $company->longitude,
    //         (float) $request->latitude,
    //         (float) $request->longitude
    //     );

    //     if ($distanceKm > (float) $company->radius_km) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => sprintf(
    //                 'Anda berada di luar radius pesantren (%s m dari batas %s m)',
    //                 number_format($distanceKm * 1000, 0),
    //                 number_format((float) $company->radius_km * 1000, 0)
    //             ),
    //             'data' => [
    //                 'distance_km' => round($distanceKm, 3),
    //                 'radius_km'   => (float) $company->radius_km,
    //             ],
    //         ], 422);
    //     }

    //     // Hitung keterlambatan dari time_in company
    //     $scheduledIn = $company->time_in
    //         ? Carbon::parse($today . ' ' . $company->time_in)
    //         : null;

    //     $lateMinutes = 0;
    //     if ($scheduledIn && $now->gt($scheduledIn)) {
    //         $lateMinutes = (int) $now->diffInMinutes($scheduledIn);
    //     }

    //     $attendance = Attendance::firstOrNew([
    //         'company_id' => $company->id,
    //         'user_id'    => $santri->id,
    //         'date'       => $today,
    //     ]);

    //     $attendance->company_id    = $company->id;
    //     $attendance->user_id       = $santri->id;
    //     $attendance->date          = $today;
    //     $attendance->marked_by     = null;
    //     $attendance->scheduled_in  = $company->time_in;
    //     $attendance->scheduled_out = $company->time_out;
    //     $attendance->time_in       = $now->format('H:i:s');
    //     $attendance->latlon_in     = $request->latitude . ',' . $request->longitude;
    //     $attendance->status        = $lateMinutes > 0 ? 'late' : 'on_time';
    //     $attendance->late_minutes  = $lateMinutes;
    //     $attendance->face_verified = false; // akan di-verify via face scan di Flutter
    //     $attendance->save();

    //     $msg = 'Check-in berhasil';
    //     if ($lateMinutes > 0) $msg .= " (terlambat {$lateMinutes} menit)";

    //     return response()->json([
    //         'status'  => true,
    //         'message' => $msg,
    //         'data'    => $this->attendanceData($attendance),
    //     ]);
    // }

    // // ============================================================
    // // CHECK-OUT
    // // POST /api/pesantren/santri/attendances/check-out
    // // Sejajar: EmployeeAttendanceController::checkOut()
    // // ============================================================
    // public function checkOut(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $request->validate([
    //         'latitude'  => 'required|numeric|between:-90,90',
    //         'longitude' => 'required|numeric|between:-180,180',
    //     ]);

    //     $santri  = $this->me();
    //     $company = $this->pesantrenOrFail();
    //     $today   = Carbon::today()->toDateString();
    //     $now     = Carbon::now();

    //     $attendance = Attendance::where('company_id', $company->id)
    //         ->where('user_id', $santri->id)
    //         ->whereDate('date', $today)
    //         ->first();

    //     if (!$attendance?->time_in) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Belum check-in hari ini',
    //         ], 422);
    //     }

    //     if ($attendance->time_out) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Sudah check-out hari ini pada pukul ' . $attendance->time_out,
    //         ], 422);
    //     }

    //     // Validasi geofence
    //     $distanceKm = $this->haversineKm(
    //         (float) $company->latitude,
    //         (float) $company->longitude,
    //         (float) $request->latitude,
    //         (float) $request->longitude
    //     );

    //     if ($distanceKm > (float) $company->radius_km) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => sprintf(
    //                 'Anda berada di luar radius pesantren (%s m dari batas %s m)',
    //                 number_format($distanceKm * 1000, 0),
    //                 number_format((float) $company->radius_km * 1000, 0)
    //             ),
    //             'data' => [
    //                 'distance_km' => round($distanceKm, 3),
    //                 'radius_km'   => (float) $company->radius_km,
    //             ],
    //         ], 422);
    //     }

    //     // Hitung pulang lebih awal
    //     $scheduledOut = $company->time_out
    //         ? Carbon::parse($today . ' ' . $company->time_out)
    //         : null;

    //     $earlyLeaveMinutes = 0;
    //     if ($scheduledOut && $now->lt($scheduledOut)) {
    //         $earlyLeaveMinutes = (int) $scheduledOut->diffInMinutes($now);
    //     }

    //     $attendance->time_out            = $now->format('H:i:s');
    //     $attendance->latlon_out          = $request->latitude . ',' . $request->longitude;
    //     $attendance->early_leave_minutes = $earlyLeaveMinutes;
    //     $attendance->save();

    //     $msg = 'Check-out berhasil';
    //     if ($earlyLeaveMinutes > 0) $msg .= " (pulang {$earlyLeaveMinutes} menit lebih awal)";

    //     return response()->json([
    //         'status'  => true,
    //         'message' => $msg,
    //         'data'    => $this->attendanceData($attendance),
    //     ]);
    // }

    // // ============================================================
    // // IS CHECKED IN
    // // GET /api/pesantren/santri/attendances/is-checkin
    // // Sejajar: EmployeeAttendanceController::isCheckedIn()
    // // ============================================================
    // public function isCheckedIn(): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $santri  = $this->me();
    //     $company = $this->pesantrenOrFail();
    //     $today   = Carbon::today()->toDateString();

    //     $attendance = Attendance::where('company_id', $company->id)
    //         ->where('user_id', $santri->id)
    //         ->whereDate('date', $today)
    //         ->first();

    //     $nextAction = 'checkin';
    //     if ($attendance?->time_in && !$attendance?->time_out) $nextAction = 'checkout';
    //     if ($attendance?->time_in && $attendance?->time_out)  $nextAction = 'done';

    //     return response()->json([
    //         'status'      => true,
    //         'message'     => 'Status absensi hari ini',
    //         'checked_in'  => (bool) $attendance?->time_in,
    //         'checked_out' => (bool) $attendance?->time_out,
    //         'next_action' => $nextAction,
    //         'data'        => $attendance ? $this->attendanceData($attendance) : null,
    //     ]);
    // }

    // // ============================================================
    // // HISTORY
    // // GET /api/pesantren/santri/attendances/history
    // // Sejajar: EmployeeAttendanceController::history()
    // // Query: limit (default 30, max 100)
    // // ============================================================
    // public function history(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $santri  = $this->me();
    //     $company = $this->pesantrenOrFail();

    //     $limit = (int) $request->get('limit', 30);
    //     $limit = max(1, min($limit, 100));

    //     $attendances = Attendance::where('company_id', $company->id)
    //         ->where('user_id', $santri->id)
    //         ->orderBy('date', 'desc')
    //         ->orderBy('id', 'desc')
    //         ->limit($limit)
    //         ->get();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Riwayat absensi berhasil diambil',
    //         'data'    => $attendances->map(fn($a) => $this->attendanceData($a))->values(),
    //     ]);
    // }

    // // ============================================================
    // // SUMMARY STATISTIK BULANAN
    // // GET /api/pesantren/santri/attendances/summary
    // // Sejajar: EmployeeAttendanceController::summary()
    // // Query: month, year
    // // ============================================================
    // public function summary(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $santri    = $this->me();
    //     $company   = $this->pesantrenOrFail();
    //     $month     = (int) $request->get('month', Carbon::now()->month);
    //     $year      = (int) $request->get('year',  Carbon::now()->year);

    //     if ($month < 1 || $month > 12) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Bulan tidak valid (1-12).',
    //         ], 422);
    //     }

    //     $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
    //     $end   = $start->copy()->endOfMonth();

    //     $attendances = Attendance::where('company_id', $company->id)
    //         ->where('user_id', $santri->id)
    //         ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
    //         ->get(['date', 'status', 'late_minutes', 'early_leave_minutes']);

    //     $hadir     = $attendances->whereIn('status', ['on_time', 'late'])->count();
    //     $terlambat = $attendances->where('status', 'late')->count();
    //     $alpha     = $attendances->where('status', 'absent')->count();

    //     // Izin yang approved bulan ini
    //     $izin = Permission::where('user_id', $santri->id)
    //         ->where('company_id', $company->id)
    //         ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
    //         ->where('is_approved', true)
    //         ->count();

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Ringkasan statistik kehadiran',
    //         'data'    => [
    //             'period' => [
    //                 'month' => $month,
    //                 'year'  => $year,
    //                 'label' => $this->bulanLabel($month) . ' ' . $year,
    //             ],
    //             'hadir'               => ['count' => $hadir,     'label' => 'Hari Hadir'],
    //             'terlambat'           => ['count' => $terlambat, 'label' => 'Terlambat'],
    //             'izin'                => ['count' => $izin,      'label' => 'Izin'],
    //             'alpha'               => ['count' => $alpha,     'label' => 'Alpha'],
    //             'total_late_minutes'  => (int) $attendances->sum('late_minutes'),
    //             'total_early_minutes' => (int) $attendances->sum('early_leave_minutes'),
    //         ],
    //     ]);
    // }

    // kode 2
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Santri)',
            ], 403));
        }
    }

    private function me(): User
    {
        return auth()->user();
    }

    private function pesantrenOrFail()
    {
        $company = $this->me()->company;
        if (!$company) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Pesantren tidak ditemukan untuk santri ini',
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
            'late_minutes'        => (int) ($a->late_minutes        ?? 0),
            'early_leave_minutes' => (int) ($a->early_leave_minutes ?? 0),
            'face_verified'       => (bool) ($a->face_verified      ?? false),
            'marked_by'           => $a->marked_by,
        ];
    }

    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? (string) $month;
    }

    // ============================================================
    // REGISTER FACE
    // POST /api/pesantren/santri/attendances/register-face
    // Sejajar: EmployeeAttendanceController::registerFace()
    // ============================================================
    public function registerFace(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $request->validate([
            'face_embedding' => 'required|string|min:20',
        ]);

        $santri = $this->me();
        $santri->face_embedding = $request->face_embedding;
        $santri->save();

        return response()->json([
            'status'  => true,
            'message' => 'Face embedding berhasil disimpan',
            'data'    => [
                'id'             => $santri->id,
                'name'           => $santri->name,
                'face_embedding' => $santri->face_embedding,
            ],
        ]);
    }

    // ============================================================
    // CHECK-IN
    // POST /api/pesantren/santri/attendances/check-in
    // Sejajar: EmployeeAttendanceController::checkIn()
    // Pesantren tidak pakai shift system — pakai time_in company
    // ============================================================
    public function checkIn(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $santri  = $this->me();
        $company = $this->pesantrenOrFail();
        $today   = Carbon::today()->toDateString();
        $now     = Carbon::now();

        // Wajib register face dulu
        if (empty($santri->face_embedding)) {
            return response()->json([
                'status'       => false,
                'face_warning' => true,
                'message'      => 'Anda belum melakukan registrasi wajah.',
                'hint'         => 'Silahkan lakukan register face terlebih dahulu melalui menu "Daftar Wajah".',
            ], 422);
        }

        // Guard double check-in
        $existing = Attendance::where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing?->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Sudah check-in hari ini pada pukul ' . $existing->time_in,
            ], 422);
        }

        // Validasi geofence radius
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
                    'Anda berada di luar radius pesantren (%s m dari batas %s m)',
                    number_format($distanceKm * 1000, 0),
                    number_format((float) $company->radius_km * 1000, 0)
                ),
                'data' => [
                    'distance_km' => round($distanceKm, 3),
                    'radius_km'   => (float) $company->radius_km,
                ],
            ], 422);
        }

        // Hitung keterlambatan dari time_in company (pesantren tidak pakai shift)
        $scheduledIn = $company->time_in
            ? Carbon::parse($today . ' ' . $company->time_in)
            : null;

        $lateMinutes = 0;
        $status      = 'on_time';

        if ($scheduledIn) {
            if ($now->gt($scheduledIn)) {
                $lateMinutes = (int) $scheduledIn->diffInMinutes($now);
                $status      = $lateMinutes > 0 ? 'late' : 'on_time';
            }
        }

        if ($existing) {
            $existing->time_in      = $now->format('H:i:s');
            $existing->scheduled_in = $scheduledIn?->format('H:i:s');
            $existing->latlon_in    = $request->latitude . ',' . $request->longitude;
            $existing->late_minutes = $lateMinutes;
            $existing->status       = $status;
            $existing->save();
            $attendance = $existing;
        } else {
            $attendance = Attendance::create([
                'company_id'    => $company->id,
                'user_id'       => $santri->id,
                'date'          => $today,
                'time_in'       => $now->format('H:i:s'),
                'scheduled_in'  => $scheduledIn?->format('H:i:s'),
                'scheduled_out' => $company->time_out ?? null,
                'latlon_in'     => $request->latitude . ',' . $request->longitude,
                'late_minutes'  => $lateMinutes,
                'status'        => $status,
            ]);
        }

        $msg = 'Check-in berhasil';
        if ($lateMinutes > 0) $msg .= " (terlambat {$lateMinutes} menit)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceData($attendance),
        ]);
    }

    // ============================================================
    // CHECK-OUT
    // POST /api/pesantren/santri/attendances/check-out
    // Sejajar: EmployeeAttendanceController::checkOut()
    // ============================================================
    public function checkOut(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $santri  = $this->me();
        $company = $this->pesantrenOrFail();
        $today   = Carbon::today()->toDateString();
        $now     = Carbon::now();

        $attendance = Attendance::where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->whereDate('date', $today)
            ->first();

        // Harus checkin dulu
        if (!$attendance || !$attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Belum check-in hari ini',
            ], 422);
        }

        // Guard double checkout
        if ($attendance->time_out) {
            return response()->json([
                'status'  => false,
                'message' => 'Sudah check-out hari ini pada pukul ' . $attendance->time_out,
            ], 422);
        }

        // Validasi geofence
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
                    'Anda berada di luar radius pesantren (%s m dari batas %s m)',
                    number_format($distanceKm * 1000, 0),
                    number_format((float) $company->radius_km * 1000, 0)
                ),
                'data' => [
                    'distance_km' => round($distanceKm, 3),
                    'radius_km'   => (float) $company->radius_km,
                ],
            ], 422);
        }

        // Hitung pulang lebih awal dari time_out company
        $scheduledOut = $company->time_out
            ? Carbon::parse($today . ' ' . $company->time_out)
            : null;

        $earlyLeaveMinutes = 0;
        if ($scheduledOut && $now->lt($scheduledOut)) {
            $earlyLeaveMinutes = (int) $scheduledOut->diffInMinutes($now);
        }

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

    // ============================================================
    // IS CHECKED IN
    // GET /api/pesantren/santri/attendances/is-checkin
    // Sejajar: EmployeeAttendanceController::isCheckedIn()
    // ============================================================
    public function isCheckedIn(): JsonResponse
    {
        $this->ensureSantri();

        $santri  = $this->me();
        $company = $this->pesantrenOrFail();
        $today   = Carbon::today()->toDateString();

        $attendance = Attendance::where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->whereDate('date', $today)
            ->first();

        $nextAction = 'checkin';
        if ($attendance?->time_in && !$attendance?->time_out) $nextAction = 'checkout';
        if ($attendance?->time_in && $attendance?->time_out)  $nextAction = 'done';

        return response()->json([
            'status'      => true,
            'message'     => 'Status absensi hari ini',
            'checked_in'  => (bool) $attendance?->time_in,
            'checked_out' => (bool) $attendance?->time_out,
            'next_action' => $nextAction,
            'data'        => $attendance ? $this->attendanceData($attendance) : null,
        ]);
    }

    // ============================================================
    // HISTORY
    // GET /api/pesantren/santri/attendances/history
    // Sejajar: EmployeeAttendanceController::history()
    // Query: limit (default 30, max 100)
    // ============================================================
    public function history(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $santri  = $this->me();
        $company = $this->pesantrenOrFail();

        $limit = (int) $request->get('limit', 30);
        $limit = max(1, min($limit, 100));

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat absensi berhasil diambil',
            'data'    => $attendances->map(fn($a) => $this->attendanceData($a))->values(),
        ]);
    }

    // ============================================================
    // SUMMARY STATISTIK BULANAN
    // GET /api/pesantren/santri/attendances/summary
    // Sejajar: EmployeeAttendanceController::summary()
    // Query: month, year
    // ============================================================
    public function summary(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $santri  = $this->me();
        $company = $this->pesantrenOrFail();
        $month   = (int) $request->get('month', Carbon::now()->month);
        $year    = (int) $request->get('year',  Carbon::now()->year);

        if ($month < 1 || $month > 12) {
            return response()->json([
                'status'  => false,
                'message' => 'Bulan tidak valid (1-12).',
            ], 422);
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'status', 'late_minutes', 'early_leave_minutes']);

        $hadir     = $attendances->whereIn('status', ['on_time', 'late'])->count();
        $terlambat = $attendances->where('status', 'late')->count();
        $alpha     = $attendances->where('status', 'absent')->count();

        $izin = Permission::where('user_id', $santri->id)
            ->where('company_id', $company->id)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->where('is_approved', true)
            ->count();

        return response()->json([
            'status'  => true,
            'message' => 'Ringkasan statistik kehadiran',
            'data'    => [
                'period' => [
                    'month' => $month,
                    'year'  => $year,
                    'label' => $this->bulanLabel($month) . ' ' . $year,
                ],
                'hadir'               => ['count' => $hadir,     'label' => 'Hari Hadir'],
                'terlambat'           => ['count' => $terlambat, 'label' => 'Terlambat'],
                'izin'                => ['count' => $izin,      'label' => 'Izin'],
                'alpha'               => ['count' => $alpha,     'label' => 'Alpha'],
                'total_late_minutes'  => (int) $attendances->sum('late_minutes'),
                'total_early_minutes' => (int) $attendances->sum('early_leave_minutes'),
            ],
        ]);
    }
}
