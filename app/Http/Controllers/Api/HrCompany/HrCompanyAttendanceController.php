<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrCompanyAttendanceController extends Controller
{


    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus HR)',
            ], 403));
        }
    }

    private function companyOrFail(): Company
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status'  => false,
                'message' => 'company_id tidak ditemukan pada user HR',
            ], 422));
        }

        $company = Company::find($companyId);

        if (!$company) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Company tidak ditemukan',
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

    private function companyArray(Company $company): array
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
        User $employee,
        string $action,
        Company $company,
        ?float $distanceKm = null
    ): array {
        return [
            'attendance_id'        => (int) $a->id,
            'employee_id'          => (int) $employee->id,
            'employee_name'        => $employee->name,
            'employee_position'    => $employee->position,
            'employee_department'  => $employee->department,
            'action'               => $action,
            'date'                 => $a->date,
            'time_in'              => $a->time_in,
            'time_out'             => $a->time_out,
            'scheduled_in'         => $a->scheduled_in,
            'scheduled_out'        => $a->scheduled_out,
            'status'               => $a->status,
            'late_minutes'         => (int)  ($a->late_minutes        ?? 0),
            'early_leave_minutes'  => (int)  ($a->early_leave_minutes ?? 0),
            'overtime_minutes'     => (int)  ($a->overtime_minutes    ?? 0),
            'face_verified'        => (bool) ($a->face_verified       ?? false),
            'marked_by'            => $a->marked_by,
            'latlon_in'            => $a->latlon_in,
            'latlon_out'           => $a->latlon_out,
            'success_time'         => $action === 'checkin' ? $a->time_in : $a->time_out,
            'company'              => $this->companyArray($company),
            'distance_km'          => $distanceKm !== null ? round($distanceKm, 3) : null,
            'shift'                => $a->relationLoaded('shift') && $a->shift ? [
                'id'         => $a->shift->id,
                'name'       => $a->shift->name,
                'start_time' => $a->shift->start_time,
                'end_time'   => $a->shift->end_time,
            ] : null,
        ];
    }

    // ============================================================
    // SHIFT RESOLVER
    // Prioritas: user_shift_overrides → shift_group → default shift
    // ============================================================

    private function resolveShift(User $employee, string $date): ?Shift
    {
        // 1. Override spesifik user
        $override = DB::table('user_shift_overrides')
            ->where('user_id', $employee->id)
            ->where('status', 'active')
            ->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
            })
            ->whereNull('deleted_at')
            ->orderByDesc('start_date')
            ->first();

        if ($override) return Shift::find($override->shift_id);

        // 2. Group shift
        $groupIds = DB::table('shift_group_users')
            ->where('user_id', $employee->id)
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
                ->where('company_id', $employee->company_id)
                ->where('start_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $date);
                })
                ->whereNull('deleted_at')
                ->orderByDesc('start_date')
                ->first();

            if ($assignment) return Shift::find($assignment->shift_id);
        }

        // 3. Shift default company
        return Shift::where('company_id', $employee->company_id)
            ->where('is_default', true)
            ->first();
    }

    private function getScheduledIn(?Shift $shift, Company $company, string $date): ?Carbon
    {
        $time = $shift ? $shift->start_time : $company->time_in;
        return $time ? Carbon::parse($date . ' ' . $time) : null;
    }

    private function getScheduledOut(?Shift $shift, Company $company, string $date): ?Carbon
    {
        $time = $shift ? $shift->end_time : $company->time_out;
        return $time ? Carbon::parse($date . ' ' . $time) : null;
    }

    private function calcLateMinutes(?Shift $shift, Company $company, Carbon $actualIn, string $date): int
    {
        $scheduledIn = $this->getScheduledIn($shift, $company, $date);
        if (!$scheduledIn) return 0;

        $gracePeriod = $shift?->grace_period_minutes ?? 0;
        $deadline    = $scheduledIn->copy()->addMinutes($gracePeriod);

        if ($actualIn->lte($deadline)) return 0;

        return (int) $actualIn->diffInMinutes($scheduledIn);
    }

    private function calcEarlyLeaveMinutes(?Shift $shift, Company $company, Carbon $actualOut, string $date): int
    {
        $scheduledOut = $this->getScheduledOut($shift, $company, $date);
        if (!$scheduledOut) return 0;
        if ($actualOut->gte($scheduledOut)) return 0;

        return (int) $scheduledOut->diffInMinutes($actualOut);
    }

    private function bulanLabel(int $month): string
    {
        return [
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
        ][$month] ?? (string) $month;
    }

    // ============================================================
    // GET SETTINGS
    // GET /api/company/hr/attendances/settings
    // ============================================================
    public function settings(): JsonResponse
    {
        $this->ensureHr();
        $company = $this->companyOrFail();

        return response()->json([
            'status'  => true,
            'message' => 'Setting attendance berhasil diambil',
            'data'    => [
                'company_id'   => (int)    $company->id,
                'company_name' => (string) $company->name,
                'latitude'     => (string) $company->latitude,
                'longitude'    => (string) $company->longitude,
                'radius_km'    => (string) $company->radius_km,
                'time_in'      => (string) $company->time_in,
                'time_out'     => (string) $company->time_out,
            ],
        ]);
    }

    // ============================================================
    // UPDATE SETTINGS
    // POST /api/company/hr/attendances/settings
    // ============================================================
    public function updateSettings(Request $request): JsonResponse
    {
        $this->ensureHr();
        $company = $this->companyOrFail();

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'required|numeric|min:0.01|max:50',
            'time_in'   => 'nullable|date_format:H:i',
            'time_out'  => 'nullable|date_format:H:i',
        ]);

        $company->update([
            'latitude'  => (string) $validated['latitude'],
            'longitude' => (string) $validated['longitude'],
            'radius_km' => (string) $validated['radius_km'],
            'time_in'   => $validated['time_in']  ?? $company->time_in,
            'time_out'  => $validated['time_out'] ?? $company->time_out,
        ]);

        $company->refresh();

        return response()->json([
            'status'  => true,
            'message' => 'Setting attendance company berhasil diperbarui',
            'data'    => [
                'company_id'   => (int)    $company->id,
                'company_name' => (string) $company->name,
                'latitude'     => (string) $company->latitude,
                'longitude'    => (string) $company->longitude,
                'radius_km'    => (string) $company->radius_km,
                'time_in'      => (string) $company->time_in,
                'time_out'     => (string) $company->time_out,
            ],
        ]);
    }

    // ============================================================
    // GET EMPLOYEES TODAY
    // GET /api/company/hr/attendances/employees
    // ============================================================
    public function employeesToday(): JsonResponse
    {
        $this->ensureHr();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();

        $employees = User::query()
            ->where('role', 'employee')
            ->where('company_id', $company->id)
            ->select(['id', 'name', 'position', 'department', 'image_url', 'face_embedding'])
            ->with(['attendances' => function ($q) use ($today, $company) {
                $q->where('company_id', $company->id)
                    ->whereDate('date', $today)
                    ->with('shift')
                    ->latest('id');
            }])
            ->get()
            ->map(function ($user) {
                $att = $user->attendances->first();

                $nextAction = 'checkin';
                if ($att?->time_in && !$att?->time_out) $nextAction = 'checkout';
                if ($att?->time_in && $att?->time_out)  $nextAction = 'done';

                return [
                    'id'             => (int) $user->id,
                    'name'           => $user->name,
                    'position'       => $user->position,
                    'department'     => $user->department,
                    'image_url'      => $user->image_url,
                    'face_embedding' => $user->face_embedding,
                    'has_face'       => !empty($user->face_embedding),
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
                        'shift'                => $att->shift ? [
                            'id'   => $att->shift->id,
                            'name' => $att->shift->name,
                        ] : null,
                    ] : null,
                    'next_action'   => $nextAction,
                    'can_checkin'   => $nextAction === 'checkin',
                    'can_checkout'  => $nextAction === 'checkout',
                    'is_done_today' => $nextAction === 'done',
                ];
            })
            ->values();

        return response()->json([
            'status'  => true,
            'message' => 'Data employee hari ini berhasil diambil',
            'data'    => [
                'date'      => $today,
                'company'   => $this->companyArray($company),
                'employees' => $employees,
            ],
        ]);
    }

    // ============================================================
    // MARK EMPLOYEE ATTENDANCE
    // POST /api/company/hr/attendances/employees/mark
    // ============================================================
    public function markEmployeeAttendance(Request $request): JsonResponse
    {
        $this->ensureHr();
        $company = $this->companyOrFail();

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'action'      => 'required|in:checkin,checkout',
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
        ]);

        $employee = User::query()
            ->where('id', $validated['employee_id'])
            ->where('role', 'employee')
            ->where('company_id', $company->id)
            ->first();

        if (!$employee) {
            return response()->json([
                'status'  => false,
                'message' => 'User bukan employee atau bukan bagian company ini',
            ], 422);
        }

        if (empty($employee->face_embedding)) {
            return response()->json([
                'status'       => false,
                'face_warning' => true,
                'message'      => 'Karyawan ini belum melakukan registrasi wajah.',
                'hint'         => 'Silahkan minta karyawan untuk melakukan register face di device akun absensi mereka terlebih dahulu.',
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
                    'Perangkat berada di luar radius attendance company (%s m dari batas %s m)',
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

        $shift        = $this->resolveShift($employee, $today);
        $scheduledIn  = $this->getScheduledIn($shift, $company, $today);
        $scheduledOut = $this->getScheduledOut($shift, $company, $today);

        $attendance = Attendance::firstOrNew([
            'company_id' => $company->id,
            'user_id'    => $employee->id,
            'date'       => $today,
        ]);

        $attendance->company_id    = $company->id;
        $attendance->user_id       = $employee->id;
        $attendance->date          = $today;
        $attendance->marked_by     = auth()->id();
        $attendance->shift_id      = $shift?->id;
        $attendance->scheduled_in  = $scheduledIn?->format('H:i:s');
        $attendance->scheduled_out = $scheduledOut?->format('H:i:s');
        $attendance->face_verified = true;

        if ($validated['action'] === 'checkin') {
            if ($attendance->time_in) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Employee sudah check-in hari ini pada pukul ' . $attendance->time_in,
                ], 422);
            }

            $lateMinutes             = $this->calcLateMinutes($shift, $company, $now, $today);
            $attendance->time_in     = $now->format('H:i:s');
            $attendance->latlon_in   = $latlon;
            $attendance->status      = $lateMinutes > 0 ? 'late' : 'on_time';
            $attendance->late_minutes = $lateMinutes;
            $attendance->save();

            $msg = 'Check-in employee berhasil';
            if ($lateMinutes > 0) $msg .= " (terlambat {$lateMinutes} menit)";

            return response()->json([
                'status'  => true,
                'message' => $msg,
                'data'    => $this->attendanceArray($attendance->load('shift'), $employee, 'checkin', $company, $distanceKm),
            ]);
        }

        if (!$attendance->time_in) {
            return response()->json([
                'status'  => false,
                'message' => 'Employee belum check-in hari ini',
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status'  => false,
                'message' => 'Employee sudah check-out hari ini pada pukul ' . $attendance->time_out,
            ], 422);
        }

        $earlyLeaveMinutes               = $this->calcEarlyLeaveMinutes($shift, $company, $now, $today);
        $attendance->time_out            = $now->format('H:i:s');
        $attendance->latlon_out          = $latlon;
        $attendance->early_leave_minutes = $earlyLeaveMinutes;
        $attendance->save();

        $msg = 'Check-out employee berhasil';
        if ($earlyLeaveMinutes > 0) $msg .= " (pulang {$earlyLeaveMinutes} menit lebih awal)";

        return response()->json([
            'status'  => true,
            'message' => $msg,
            'data'    => $this->attendanceArray($attendance->load('shift'), $employee, 'checkout', $company, $distanceKm),
        ]);
    }

    // ============================================================
    // EMPLOYEE HISTORY
    // GET /api/company/hr/attendances/employees/{id}/history
    // ============================================================
    public function employeeHistory(Request $request, int $id): JsonResponse
    {
        $this->ensureHr();
        $company = $this->companyOrFail();

        $employee = User::query()
            ->where('id', $id)
            ->where('role', 'employee')
            ->where('company_id', $company->id)
            ->firstOrFail();

        $perPage = (int) $request->get('per_page', 30);

        $history = Attendance::query()
            ->where('company_id', $company->id)
            ->where('user_id', $employee->id)
            ->with('shift')
            ->latest('date')
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
            'shift'               => $a->shift ? [
                'id'         => $a->shift->id,
                'name'       => $a->shift->name,
                'start_time' => $a->shift->start_time,
                'end_time'   => $a->shift->end_time,
            ] : null,
        ])->values()->toArray();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat attendance employee berhasil diambil',
            'data'    => $data,
            'meta'    => [
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'per_page'     => $history->perPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    // ============================================================
    // TODAY SUMMARY
    // GET /api/company/hr/attendances/employees/today
    // ============================================================
    public function todaySummary(): JsonResponse
    {
        $this->ensureHr();
        $company = $this->companyOrFail();
        $today   = Carbon::today()->toDateString();

        $totalEmployees = User::where('role', 'employee')
            ->where('company_id', $company->id)
            ->count();

        $attendances = Attendance::where('company_id', $company->id)
            ->whereDate('date', $today)
            ->get();

        $checkin  = $attendances->whereNotNull('time_in')->count();
        $checkout = $attendances->whereNotNull('time_out')->count();
        $late     = $attendances->where('status', 'late')->count();
        $absent   = max(0, $totalEmployees - $checkin);

        return response()->json([
            'status'  => true,
            'message' => 'Summary attendance hari ini berhasil diambil',
            'data'    => [
                'total'    => $totalEmployees,
                'checkin'  => $checkin,
                'checkout' => $checkout,
                'late'     => $late,
                'absent'   => $absent,
            ],
        ]);
    }

    // ============================================================
    // EXPORT PDF — SEMUA KARYAWAN (rekap bulanan)
    // GET /api/company/hr/attendances/employees/export
    //
    // Query params: month (required), year (required)
    //
    // Install: composer require barryvdh/laravel-dompdf
    // ============================================================
    public function exportAllPdf(Request $request)
    {
        $this->ensureHr();
        $company = $this->companyOrFail();

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $validated['month'];
        $year  = (int) $validated['year'];

        $employees = User::where('company_id', $company->id)
            ->where('role', 'employee')
            ->orderBy('name')
            ->get(['id', 'name', 'position', 'department']);

        $attendances = Attendance::where('company_id', $company->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with('shift')
            ->orderBy('user_id')
            ->orderBy('date')
            ->get();

        $grouped = $attendances->groupBy('user_id');

        $summaryData = $employees->map(function ($emp) use ($grouped) {
            $atts = $grouped->get($emp->id, collect());
            return [
                'employee'        => $emp,
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
        $fileName    = 'rekap-absensi-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_attendance_all', [
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
    // EXPORT PDF — 1 KARYAWAN (detail bulanan)
    // GET /api/company/hr/attendances/employees/{id}/history/export
    //
    // Query params: month (required), year (required)
    // ============================================================
    public function exportEmployeePdf(Request $request, int $id)
    {
        $this->ensureHr();
        $company = $this->companyOrFail();

        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year'  => 'required|integer|min:2020|max:2099',
        ]);

        $month = (int) $validated['month'];
        $year  = (int) $validated['year'];

        $employee = User::where('id', $id)
            ->where('company_id', $company->id)
            ->where('role', 'employee')
            ->firstOrFail(['id', 'name', 'position', 'department']);

        $attendances = Attendance::where('company_id', $company->id)
            ->where('user_id', $employee->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with('shift')
            ->orderBy('date')
            ->get();

        $periodLabel = $this->bulanLabel($month) . ' ' . $year;
        $fileName    = 'absensi-' . strtolower(str_replace(' ', '-', $employee->name))
            . '-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_attendance_employee', [
            'company'     => $company,
            'employee'    => $employee,
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
}
