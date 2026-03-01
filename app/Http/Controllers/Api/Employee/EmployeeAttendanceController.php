<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Leaves;
use App\Models\Permission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeAttendanceController extends Controller
{
    // ==========================
    // Helper: Pastikan role employee
    // ==========================
    private function ensureEmployee()
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus employee)'
            ], 403));
        }
    }

    // =========================================
    // EMPLOYEE: REGISTER / UPDATE FACE EMBEDDING (optional)
    // =========================================
    public function registerFace(Request $request)
    {
        $this->ensureEmployee();

        $request->validate([
            'face_embedding' => 'required|string|min:20',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $user->face_embedding = $request->face_embedding;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Face embedding berhasil disimpan',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'face_embedding' => $user->face_embedding,
            ]
        ], 200);
    }

    // ==========================
    // EMPLOYEE: CHECK-IN (SELF)
    // ==========================
    public function checkIn(Request $request)
    {
        $this->ensureEmployee();

        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        // Cegah double check-in (SELF = marked_by null)
        $exists = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by')
            ->first();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah check-in hari ini'
            ], 422);
        }

        $attendance = new Attendance();
        $attendance->company_id = auth()->user()->company_id; // ✅
        $attendance->user_id = auth()->id();
        $attendance->marked_by = null; // SELF
        $attendance->date = $today;
        $attendance->time_in = Carbon::now()->format('H:i:s');
        $attendance->latlon_in = $request->latitude . ',' . $request->longitude;
        $attendance->status = 'on_time';
        $attendance->save();

        return response()->json([
            'status' => true,
            'message' => 'Check-in berhasil',
            'attendance' => $attendance
        ], 200);
    }

    // ==========================
    // EMPLOYEE: CHECK-OUT (SELF)
    // ==========================
    public function checkOut(Request $request)
    {
        $this->ensureEmployee();

        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by')
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'Belum check-in'
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah check-out hari ini'
            ], 422);
        }

        $attendance->time_out = Carbon::now()->format('H:i:s');
        $attendance->latlon_out = $request->latitude . ',' . $request->longitude;
        $attendance->save();

        return response()->json([
            'status' => true,
            'message' => 'Check-out berhasil',
            'attendance' => $attendance
        ], 200);
    }

    // ==========================
    // EMPLOYEE: STATUS HARI INI
    // ==========================
    public function isCheckedIn()
    {
        $this->ensureEmployee();

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by')
            ->first();

        return response()->json([
            'status' => true,
            'checked_in' => $attendance ? true : false,
            'checked_out' => $attendance && $attendance->time_out ? true : false,
            'attendance' => $attendance
        ], 200);
    }

    // ==========================
    // EMPLOYEE: HISTORY (SELF)
    // ==========================
    public function history(Request $request)
    {
        $this->ensureEmployee();

        $limit = (int) ($request->query('limit', 30));
        if ($limit <= 0) $limit = 30;
        if ($limit > 100) $limit = 100;

        $data = Attendance::where('user_id', auth()->id())
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $data
        ], 200);
    }

    // summary
    private function user(Request $request)
    {
        return $request->user();
    }

    private function userId(Request $request): int
    {
        return (int) $this->user($request)->id;
    }

    private function companyId(Request $request): int
    {
        return (int) $this->user($request)->company_id;
    }

    /**
     * GET /api/company/employee/stats/summary
     *
     * Query params (opsional):
     *   month = bulan (1-12), default bulan ini
     *   year  = tahun (YYYY), default tahun ini
     *
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "period": { "month": 3, "year": 2026, "label": "Maret 2026" },
     *     "hadir":    { "count": 18, "label": "Hari Hadir" },
     *     "terlambat":{ "count": 3,  "label": "Terlambat" },
     *     "izin":     { "count": 2,  "label": "Izin" },
     *     "cuti":     { "count": 1,  "label": "Cuti" },
     *     "alpha":    { "count": 0,  "label": "Alpha" }
     *   }
     * }
     */
    public function summary(Request $request)
    {
        $userId    = $this->userId($request);
        $companyId = $this->companyId($request);

        $month = (int) $request->get('month', Carbon::now()->month);
        $year  = (int) $request->get('year',  Carbon::now()->year);

        // Validasi sederhana
        if ($month < 1 || $month > 12) {
            return response()->json([
                'success' => false,
                'message' => 'Bulan tidak valid (1-12).',
            ], 422);
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // ── Attendances bulan ini milik user ──
        $attendances = Attendance::where('user_id', $userId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'status']);

        // Hitung hadir = on_time + overtime + guest (yang masuk kerja)
        $hadir     = $attendances->whereIn('status', ['on_time', 'overtime', 'guest'])->count();
        $terlambat = $attendances->where('status', 'late')->count();
        $alpha     = $attendances->where('status', 'absent')->count();

        // ── Izin (permissions) bulan ini yang approved ──
        $izin = Permission::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->whereBetween('date_permission', [$start->toDateString(), $end->toDateString()])
            ->where('is_approved', true)
            ->count();

        // ── Cuti (leaves) yang approved & overlap bulan ini ──
        $cuti = Leaves::where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->toDateString())
            ->where('end_date', '>=', $start->toDateString())
            ->count();

        // Label bulan Indonesia
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
            'data' => [
                'period' => [
                    'month' => $month,
                    'year'  => $year,
                    'label' => ($bulanLabel[$month] ?? $month) . ' ' . $year,
                ],
                'hadir'     => ['count' => $hadir,     'label' => 'Hari Hadir'],
                'terlambat' => ['count' => $terlambat,  'label' => 'Terlambat'],
                'izin'      => ['count' => $izin,       'label' => 'Izin'],
                'cuti'      => ['count' => $cuti,       'label' => 'Cuti'],
                'alpha'     => ['count' => $alpha,      'label' => 'Alpha'],
            ],
        ]);
    }
}
