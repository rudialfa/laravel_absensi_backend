<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SantriAttendanceController extends Controller
{
    // // ==============================
    // // Helper: Pastikan role santri
    // // ==============================
    // private function ensureSantri()
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'santri') {
    //         abort(response()->json([
    //             'status' => false,
    //             'message' => 'Akses ditolak (khusus santri)'
    //         ], 403));
    //     }
    // }

    // // =========================================
    // // SANTRI: REGISTER / UPDATE FACE EMBEDDING
    // // =========================================
    // public function registerFace(Request $request)
    // {
    //     $this->ensureSantri();

    //     $request->validate([
    //         // format: "0.123,0.456,..." (string panjang)
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
    // // SANTRI: CHECK-IN (SELF)
    // // ==========================
    // public function checkIn(Request $request)
    // {
    //     $this->ensureSantri();

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
    // // SANTRI: CHECK-OUT (SELF)
    // // ==========================
    // public function checkOut(Request $request)
    // {
    //     $this->ensureSantri();

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
    // // SANTRI: STATUS HARI INI
    // // ==========================
    // public function isCheckedIn()
    // {
    //     $this->ensureSantri();

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
    // // SANTRI: HISTORY (SELF)
    // // ==========================
    // public function history(Request $request)
    // {
    //     $this->ensureSantri();

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

    // kode 2
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }

    private function pesantrenOrFail(): Company
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status'  => false,
                'message' => 'company_id tidak ditemukan pada user ustadz',
            ], 422));
        }

        $company = Company::find($companyId);

        if (!$company) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Pesantren tidak ditemukan',
            ], 404));
        }

        return $company;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function pesantrenArray(Company $company): array
    {
        return [
            'id'        => (int)    $company->id,
            'name'      => (string) $company->name,
            'latitude'  => (string) $company->latitude,
            'longitude' => (string) $company->longitude,
            'radius_km' => (string) $company->radius_km,
            'time_in'   => (string) $company->time_in,
            'time_out'  => (string) $company->time_out,
        ];
    }

    private function attendanceArray(
        Attendance $a,
        User $user,
        string $action,
        Company $company,
        ?float $distanceKm = null
    ): array {
        return [
            'attendance_id'       => (int) $a->id,
            'user_id'             => (int) $user->id,
            'user_name'           => $user->name,
            'user_position'       => $user->position,
            'action'              => $action,
            'date'                => $a->date,
            'time_in'             => $a->time_in,
            'time_out'            => $a->time_out,
            'scheduled_in'        => $a->scheduled_in,
            'scheduled_out'       => $a->scheduled_out,
            'status'              => $a->status,
            'late_minutes'        => (int)  ($a->late_minutes        ?? 0),
            'early_leave_minutes' => (int)  ($a->early_leave_minutes ?? 0),
            'overtime_minutes'    => (int)  ($a->overtime_minutes    ?? 0),
            'face_verified'       => (bool) ($a->face_verified       ?? false),
            'marked_by'           => $a->marked_by,
            'latlon_in'           => $a->latlon_in,
            'latlon_out'          => $a->latlon_out,
            'success_time'        => $action === 'checkin' ? $a->time_in : $a->time_out,
            'pesantren'           => $this->pesantrenArray($company),
            'distance_km'         => $distanceKm !== null ? round($distanceKm, 3) : null,
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
    // USTADZ CHECK-IN (absensi diri sendiri)
    // POST /api/pesantren/attendances/check-in
    // ============================================================
    public function checkIn(Request $request): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $ustadz  = auth()->user();

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $distanceKm = $this->haversineKm(
            (float) $company->latitude,
            (float) $company->longitude,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if ($distanceKm > (float) $company->radius_km) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    'Perangkat berada di luar radius pesantren (%s m dari batas %s m)',
                    number_format($distanceKm * 1000, 0),
                    number_format((float) $company->radius_km * 1000, 0)
                ),
                'data' => [
                    'distance_km' => round($distanceKm, 3),
                    'radius_km'   => (float) $company->radius_km,
                ],
            ], 422);
        }

        $today  = Carbon::today()->toDateString();
        $now    = Carbon::now();
        $latlon = $validated['latitude'] . ',' . $validated['longitude'];

        $attendance = Attendance::firstOrNew([
            'company_id' => $company->id,
            'user_id'    => $ustadz->id,
            'date'       => $today,
        ]);

        if ($attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda sudah check-in hari ini pada pukul ' . $attendance->time_in,
            ], 422);
        }

        // Hitung keterlambatan berdasarkan time_in company
        $scheduledIn  = $company->time_in
            ? Carbon::parse($today . ' ' . $company->time_in)
            : null;

        $lateMinutes = 0;
        if ($scheduledIn && $now->gt($scheduledIn)) {
            $lateMinutes = (int) $now->diffInMinutes($scheduledIn);
        }

        $attendance->company_id    = $company->id;
        $attendance->user_id       = $ustadz->id;
        $attendance->date          = $today;
        $attendance->time_in       = $now->format('H:i:s');
        $attendance->latlon_in     = $latlon;
        $attendance->scheduled_in  = $company->time_in;
        $attendance->scheduled_out = $company->time_out;
        $attendance->status        = $lateMinutes > 0 ? 'late' : 'on_time';
        $attendance->late_minutes  = $lateMinutes;
        $attendance->face_verified = false; // ustadz tidak pakai face scan
        $attendance->marked_by     = null;  // mandiri
        $attendance->save();

        $msg = 'Check-in berhasil';
        if ($lateMinutes > 0) $msg .= " (terlambat {$lateMinutes} menit)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceArray($attendance, $ustadz, 'checkin', $company, $distanceKm),
        ]);
    }

    // ============================================================
    // USTADZ CHECK-OUT (absensi diri sendiri)
    // POST /api/pesantren/attendances/check-out
    // ============================================================
    public function checkOut(Request $request): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $ustadz  = auth()->user();

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $distanceKm = $this->haversineKm(
            (float) $company->latitude,
            (float) $company->longitude,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if ($distanceKm > (float) $company->radius_km) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    'Perangkat berada di luar radius pesantren (%s m dari batas %s m)',
                    number_format($distanceKm * 1000, 0),
                    number_format((float) $company->radius_km * 1000, 0)
                ),
                'data' => [
                    'distance_km' => round($distanceKm, 3),
                    'radius_km'   => (float) $company->radius_km,
                ],
            ], 422);
        }

        $today  = Carbon::today()->toDateString();
        $now    = Carbon::now();
        $latlon = $validated['latitude'] . ',' . $validated['longitude'];

        $attendance = Attendance::where('company_id', $company->id)
            ->where('user_id', $ustadz->id)
            ->whereDate('date', $today)
            ->first();

        if (!$attendance || !$attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda belum check-in hari ini',
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda sudah check-out hari ini pada pukul ' . $attendance->time_out,
            ], 422);
        }

        // Hitung pulang lebih awal
        $scheduledOut = $company->time_out
            ? Carbon::parse($today . ' ' . $company->time_out)
            : null;

        $earlyLeaveMinutes = 0;
        if ($scheduledOut && $now->lt($scheduledOut)) {
            $earlyLeaveMinutes = (int) $scheduledOut->diffInMinutes($now);
        }

        $attendance->time_out            = $now->format('H:i:s');
        $attendance->latlon_out          = $latlon;
        $attendance->early_leave_minutes = $earlyLeaveMinutes;
        $attendance->save();

        $msg = 'Check-out berhasil';
        if ($earlyLeaveMinutes > 0) $msg .= " (pulang {$earlyLeaveMinutes} menit lebih awal)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceArray($attendance, $ustadz, 'checkout', $company, $distanceKm),
        ]);
    }

    // ============================================================
    // IS CHECKED IN (status absensi diri sendiri hari ini)
    // GET /api/pesantren/attendances/is-checkin
    // ============================================================
    public function isCheckedIn(): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $ustadz  = auth()->user();
        $today   = Carbon::today()->toDateString();

        $attendance = Attendance::where('company_id', $company->id)
            ->where('user_id', $ustadz->id)
            ->whereDate('date', $today)
            ->first();

        $nextAction = 'checkin';
        if ($attendance?->time_in && !$attendance?->time_out) $nextAction = 'checkout';
        if ($attendance?->time_in && $attendance?->time_out)  $nextAction = 'done';

        return response()->json([
            'status'  => true,
            'message' => 'Status absensi hari ini',
            'data'    => [
                'date'        => $today,
                'is_checkin'  => (bool) $attendance?->time_in,
                'is_checkout' => (bool) $attendance?->time_out,
                'next_action' => $nextAction,
                'attendance'  => $attendance ? [
                    'time_in'             => $attendance->time_in,
                    'time_out'            => $attendance->time_out,
                    'status'              => $attendance->status,
                    'late_minutes'        => (int)  ($attendance->late_minutes        ?? 0),
                    'early_leave_minutes' => (int)  ($attendance->early_leave_minutes ?? 0),
                ] : null,
            ],
        ]);
    }

    // ============================================================
    // HISTORY ABSENSI DIRI SENDIRI
    // GET /api/pesantren/attendances/history
    // Query: month, year, per_page
    // ============================================================
    public function history(Request $request): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $ustadz  = auth()->user();

        $month   = (int) $request->get('month', now()->month);
        $tahun   = (int) $request->get('year',  now()->year);
        $perPage = (int) $request->get('per_page', 30);

        $history = Attendance::where('company_id', $company->id)
            ->where('user_id', $ustadz->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $tahun)
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        $data = collect($history->items())->map(fn($a) => [
            'date'                => (string) $a->date,
            'time_in'             => $a->time_in  ? (string) $a->time_in  : null,
            'time_out'            => $a->time_out ? (string) $a->time_out : null,
            'scheduled_in'        => $a->scheduled_in  ? (string) $a->scheduled_in  : null,
            'scheduled_out'       => $a->scheduled_out ? (string) $a->scheduled_out : null,
            'status'              => (string) ($a->status ?? 'absent'),
            'late_minutes'        => (int)  ($a->late_minutes        ?? 0),
            'early_leave_minutes' => (int)  ($a->early_leave_minutes ?? 0),
            'overtime_minutes'    => (int)  ($a->overtime_minutes    ?? 0),
            'face_verified'       => (bool) ($a->face_verified       ?? false),
        ])->values()->toArray();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat absensi berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'month'        => $month,
                'year'         => $tahun,
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'per_page'     => $history->perPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    // ============================================================
    // SUMMARY ABSENSI DIRI SENDIRI
    // GET /api/pesantren/attendances/summary
    // Query: month, year
    // ============================================================
    public function summary(Request $request): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $ustadz  = auth()->user();

        $month = (int) $request->get('month', now()->month);
        $tahun = (int) $request->get('year',  now()->year);

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $ustadz->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $tahun)
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Summary absensi berhasil diambil',
            'data'    => [
                'month'               => $month,
                'year'                => $tahun,
                'label'               => $this->bulanLabel($month) . ' ' . $tahun,
                'total_hari'          => $attendances->count(),
                'hadir'               => $attendances->whereNotNull('time_in')->count(),
                'on_time'             => $attendances->where('status', 'on_time')->count(),
                'late'                => $attendances->where('status', 'late')->count(),
                'absent'              => $attendances->whereNull('time_in')->count(),
                'total_late_minutes'  => (int) $attendances->sum('late_minutes'),
                'total_early_minutes' => (int) $attendances->sum('early_leave_minutes'),
            ],
        ]);
    }

    // ============================================================
    // SANTRI TODAY (list absensi santri hari ini)
    // GET /api/pesantren/attendances/santri
    // Sejajar: HrCompanyAttendanceController::employeesToday()
    // ============================================================
    public function santriToday()
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $today   = Carbon::today()->toDateString();

        $santriList = User::query()
            ->where('role', 'santri')
            ->where('company_id', $company->id)
            ->select(['id', 'name', 'position', 'department', 'image_url', 'face_embedding'])
            ->with(['attendances' => function ($q) use ($today, $company) {
                $q->where('company_id', $company->id)
                    ->whereDate('date', $today)
                    ->latest('id');
            }])
            ->get()
            ->map(function ($santri) {
                $att = $santri->attendances->first();

                $nextAction = 'checkin';
                if ($att?->time_in && !$att?->time_out) $nextAction = 'checkout';
                if ($att?->time_in && $att?->time_out)  $nextAction = 'done';

                return [
                    'id'             => (int) $santri->id,
                    'name'           => $santri->name,
                    'position'       => $santri->position,    // bisa dipakai untuk kelas/kamar
                    'department'     => $santri->department,  // bisa dipakai untuk angkatan
                    'image_url'      => $santri->image_url,
                    'face_embedding' => $santri->face_embedding,
                    'has_face'       => !empty($santri->face_embedding),
                    'attendance'     => $att ? [
                        'status'               => $att->status,
                        'time_in'              => $att->time_in,
                        'time_out'             => $att->time_out,
                        'scheduled_in'         => $att->scheduled_in,
                        'scheduled_out'        => $att->scheduled_out,
                        'late_minutes'         => (int)  ($att->late_minutes        ?? 0),
                        'early_leave_minutes'  => (int)  ($att->early_leave_minutes ?? 0),
                        'face_verified'        => (bool) ($att->face_verified       ?? false),
                        'marked_by'            => $att->marked_by,
                    ] : null,
                    'next_action'   => $nextAction,
                    'can_checkin'   => $nextAction === 'checkin',
                    'can_checkout'  => $nextAction === 'checkout',
                    'is_done_today' => $nextAction === 'done',
                ];
            })
            ->values();

        // Summary hari ini
        $totalSantri = User::where('role', 'santri')
            ->where('company_id', $company->id)
            ->count();

        $hadir  = $santriList->filter(fn($s) => $s['attendance'] !== null)->count();
        $absent = max(0, $totalSantri - $hadir);
        $late = $santriList->filter(fn($s) => ($s['attendance']['status'] ?? null) === 'late')->count();

        return response()->json([
            'status'  => true,
            'message' => 'Data absensi santri hari ini berhasil diambil',
            'data'    => [
                'date'      => $today,
                'pesantren' => $this->pesantrenArray($company),
                'summary'   => [
                    'total'  => $totalSantri,
                    'hadir'  => $hadir,
                    'late'   => $late,
                    'absent' => $absent,
                ],
                'santri'    => $santriList,
            ],
        ]);
    }

    // ============================================================
    // MARK SANTRI ATTENDANCE (absensi santri manual oleh ustadz)
    // POST /api/pesantren/attendances/santri/mark
    // Sejajar: HrCompanyAttendanceController::markEmployeeAttendance()
    // ============================================================
    public function markSantriAttendance(Request $request): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();

        $validated = $request->validate([
            'santri_id' => 'required|exists:users,id',
            'action'    => 'required|in:checkin,checkout',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $santri = User::query()
            ->where('id', $validated['santri_id'])
            ->where('role', 'santri')
            ->where('company_id', $company->id)
            ->first();

        if (!$santri) {
            return response()->json([
                'status'  => false,
                'message' => 'User bukan santri atau bukan bagian pesantren ini',
            ], 422);
        }

        if (empty($santri->face_embedding)) {
            return response()->json([
                'status'       => false,
                'face_warning' => true,
                'message'      => 'Santri ini belum melakukan registrasi wajah.',
                'hint'         => 'Silahkan minta santri untuk melakukan register face terlebih dahulu.',
            ], 422);
        }

        $distanceKm = $this->haversineKm(
            (float) $company->latitude,
            (float) $company->longitude,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if ($distanceKm > (float) $company->radius_km) {
            return response()->json([
                'status'  => false,
                'message' => sprintf(
                    'Perangkat berada di luar radius pesantren (%s m dari batas %s m)',
                    number_format($distanceKm * 1000, 0),
                    number_format((float) $company->radius_km * 1000, 0)
                ),
                'data' => [
                    'distance_km' => round($distanceKm, 3),
                    'radius_km'   => (float) $company->radius_km,
                ],
            ], 422);
        }

        $today  = Carbon::today()->toDateString();
        $now    = Carbon::now();
        $latlon = $validated['latitude'] . ',' . $validated['longitude'];

        $scheduledIn  = $company->time_in
            ? Carbon::parse($today . ' ' . $company->time_in)
            : null;
        $scheduledOut = $company->time_out
            ? Carbon::parse($today . ' ' . $company->time_out)
            : null;

        $attendance = Attendance::firstOrNew([
            'company_id' => $company->id,
            'user_id'    => $santri->id,
            'date'       => $today,
        ]);

        $attendance->company_id    = $company->id;
        $attendance->user_id       = $santri->id;
        $attendance->date          = $today;
        $attendance->marked_by     = auth()->id(); // dicatat siapa ustadz yang mark
        $attendance->scheduled_in  = $scheduledIn?->format('H:i:s');
        $attendance->scheduled_out = $scheduledOut?->format('H:i:s');
        $attendance->face_verified = true; // ustadz yang verifikasi secara langsung

        if ($validated['action'] === 'checkin') {
            if ($attendance->time_in) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Santri sudah check-in hari ini pada pukul ' . $attendance->time_in,
                ], 422);
            }

            $lateMinutes = 0;
            if ($scheduledIn && $now->gt($scheduledIn)) {
                $lateMinutes = (int) $now->diffInMinutes($scheduledIn);
            }

            $attendance->time_in      = $now->format('H:i:s');
            $attendance->latlon_in    = $latlon;
            $attendance->status       = $lateMinutes > 0 ? 'late' : 'on_time';
            $attendance->late_minutes = $lateMinutes;
            $attendance->save();

            $msg = 'Check-in santri berhasil';
            if ($lateMinutes > 0) $msg .= " (terlambat {$lateMinutes} menit)";

            return response()->json([
                'status'  => true,
                'message' => $msg,
                'data'    => $this->attendanceArray($attendance, $santri, 'checkin', $company, $distanceKm),
            ]);
        }

        // action === checkout
        if (!$attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Santri belum check-in hari ini',
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status'  => false,
                'message' => 'Santri sudah check-out hari ini pada pukul ' . $attendance->time_out,
            ], 422);
        }

        $earlyLeaveMinutes = 0;
        if ($scheduledOut && $now->lt($scheduledOut)) {
            $earlyLeaveMinutes = (int) $scheduledOut->diffInMinutes($now);
        }

        $attendance->time_out            = $now->format('H:i:s');
        $attendance->latlon_out          = $latlon;
        $attendance->early_leave_minutes = $earlyLeaveMinutes;
        $attendance->save();

        $msg = 'Check-out santri berhasil';
        if ($earlyLeaveMinutes > 0) $msg .= " (pulang {$earlyLeaveMinutes} menit lebih awal)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceArray($attendance, $santri, 'checkout', $company, $distanceKm),
        ]);
    }

    // ============================================================
    // SANTRI HISTORY (riwayat absensi satu santri)
    // GET /api/pesantren/attendances/santri/{id}/history
    // Sejajar: HrCompanyAttendanceController::employeeHistory()
    // Query: month, year, per_page
    // ============================================================
    public function santriHistory(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();

        $santri = User::query()
            ->where('id', $id)
            ->where('role', 'santri')
            ->where('company_id', $company->id)
            ->firstOrFail();

        $month   = (int) $request->get('month', now()->month);
        $tahun   = (int) $request->get('year',  now()->year);
        $perPage = (int) $request->get('per_page', 30);

        $history = Attendance::query()
            ->where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $tahun)
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        $data = collect($history->items())->map(fn($a) => [
            'date'                => (string) $a->date,
            'time_in'             => $a->time_in  ? (string) $a->time_in  : null,
            'time_out'            => $a->time_out ? (string) $a->time_out : null,
            'scheduled_in'        => $a->scheduled_in  ? (string) $a->scheduled_in  : null,
            'scheduled_out'       => $a->scheduled_out ? (string) $a->scheduled_out : null,
            'status'              => (string) ($a->status ?? 'absent'),
            'late_minutes'        => (int)  ($a->late_minutes        ?? 0),
            'early_leave_minutes' => (int)  ($a->early_leave_minutes ?? 0),
            'face_verified'       => (bool) ($a->face_verified       ?? false),
            'marked_by'           => $a->marked_by,
        ])->values()->toArray();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat absensi santri berhasil diambil',
            'santri'  => [
                'id'       => $santri->id,
                'name'     => $santri->name,
                'position' => $santri->position,
            ],
            'data'    => $data,
            'meta'    => [
                'month'        => $month,
                'year'         => $tahun,
                'label'        => $this->bulanLabel($month) . ' ' . $tahun,
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'per_page'     => $history->perPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    // ============================================================
    // TODAY SUMMARY (ringkasan semua absensi hari ini)
    // GET /api/pesantren/attendances/summary (alias endpoint dashboard)
    // Sejajar: HrCompanyAttendanceController::todaySummary()
    // ============================================================
    public function todaySummary(): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();
        $today   = Carbon::today()->toDateString();

        $totalSantri = User::where('role', 'santri')
            ->where('company_id', $company->id)
            ->count();

        $totalUstadz = User::where('role', 'ustadz')
            ->where('company_id', $company->id)
            ->count();

        $santriAttendances = Attendance::where('company_id', $company->id)
            ->whereDate('date', $today)
            ->whereHas('user', fn($q) => $q->where('role', 'santri'))
            ->get();

        $ustadzAttendances = Attendance::where('company_id', $company->id)
            ->whereDate('date', $today)
            ->whereHas('user', fn($q) => $q->where('role', 'ustadz'))
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Summary absensi hari ini berhasil diambil',
            'data'    => [
                'date'    => $today,
                'santri'  => [
                    'total'    => $totalSantri,
                    'checkin'  => $santriAttendances->whereNotNull('time_in')->count(),
                    'checkout' => $santriAttendances->whereNotNull('time_out')->count(),
                    'late'     => $santriAttendances->where('status', 'late')->count(),
                    'absent'   => max(0, $totalSantri - $santriAttendances->whereNotNull('time_in')->count()),
                ],
                'ustadz'  => [
                    'total'    => $totalUstadz,
                    'checkin'  => $ustadzAttendances->whereNotNull('time_in')->count(),
                    'checkout' => $ustadzAttendances->whereNotNull('time_out')->count(),
                    'late'     => $ustadzAttendances->where('status', 'late')->count(),
                    'absent'   => max(0, $totalUstadz - $ustadzAttendances->whereNotNull('time_in')->count()),
                ],
            ],
        ]);
    }

    // ============================================================
    // EXPORT PDF — SEMUA SANTRI (rekap bulanan)
    // GET /api/pesantren/attendances/export
    // Sejajar: HrCompanyAttendanceController::exportAllPdf()
    // Query: month (required), year (required)
    // ============================================================
    public function exportAllPdf(Request $request)
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $validated['month'];
        $year  = (int) $validated['year'];

        $santriList = User::where('company_id', $company->id)
            ->where('role', 'santri')
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'department']);

        $attendances = Attendance::where('company_id', $company->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('user_id')
            ->orderBy('date')
            ->get();

        $grouped = $attendances->groupBy('user_id');

        $summaryData = $santriList->map(function ($santri) use ($grouped) {
            $atts = $grouped->get($santri->id, collect());
            return [
                'santri'          => $santri,
                'attendances'     => $atts,
                'total_days'      => $atts->count(),
                'hadir'           => $atts->whereNotNull('time_in')->count(),
                'on_time'         => $atts->where('status', 'on_time')->count(),
                'late'            => $atts->where('status', 'late')->count(),
                'absent'          => $atts->whereNull('time_in')->count(),
                'total_late_min'  => $atts->sum('late_minutes'),
                'total_early_min' => $atts->sum('early_leave_minutes'),
            ];
        });

        $periodLabel = $this->bulanLabel($month) . ' ' . $year;
        $fileName    = 'rekap-absensi-santri-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_attendance_all', [
            'company'     => $company,
            'periodLabel' => $periodLabel,
            'month'       => $month,
            'year'        => $year,
            'summaryData' => $summaryData,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }

    // ============================================================
    // EXPORT PDF — 1 SANTRI (detail bulanan)
    // GET /api/pesantren/attendances/santri/{id}/history/export
    // Sejajar: HrCompanyAttendanceController::exportEmployeePdf()
    // Query: month (required), year (required)
    // ============================================================
    public function exportSantriPdf(Request $request, int $id)
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $validated['month'];
        $year  = (int) $validated['year'];

        $santri = User::where('id', $id)
            ->where('company_id', $company->id)
            ->where('role', 'santri')
            ->firstOrFail(['id', 'name', 'position', 'department']);

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $santri->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        $periodLabel = $this->bulanLabel($month) . ' ' . $year;
        $fileName    = 'absensi-' . strtolower(str_replace(' ', '-', $santri->name))
            . '-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_attendance_santri', [
            'company'     => $company,
            'santri'      => $santri,
            'periodLabel' => $periodLabel,
            'attendances' => $attendances,
            'total'       => $attendances->count(),
            'hadir'       => $attendances->whereNotNull('time_in')->count(),
            'onTime'      => $attendances->where('status', 'on_time')->count(),
            'late'        => $attendances->where('status', 'late')->count(),
            'absent'      => $attendances->whereNull('time_in')->count(),
            'totalLate'   => $attendances->sum('late_minutes'),
            'totalEarly'  => $attendances->sum('early_leave_minutes'),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }

    // ============================================================
    // SETTINGS (baca setting absensi pesantren)
    // GET /api/pesantren/attendances/settings  (opsional, info saja)
    // Sejajar: HrCompanyAttendanceController::settings()
    // ============================================================
    public function settings(): JsonResponse
    {
        $this->ensureUstadz();
        $company = $this->pesantrenOrFail();

        return response()->json([
            'status'  => true,
            'message' => 'Setting absensi pesantren berhasil diambil',
            'data'    => [
                'company_id'    => (int)    $company->id,
                'company_name'  => (string) $company->name,
                'latitude'      => (string) $company->latitude,
                'longitude'     => (string) $company->longitude,
                'radius_km'     => (string) $company->radius_km,
                'time_in'       => (string) $company->time_in,
                'time_out'      => (string) $company->time_out,
            ],
        ]);
    }
}
