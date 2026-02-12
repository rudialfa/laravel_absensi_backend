<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class HrCompanyAttendanceController extends Controller
{
    // ==========================
    // Helper: Pastikan role hr
    // ==========================
    private function ensureHr()
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus HR)'
            ], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    private function companyOrFail()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'company_id tidak ditemukan pada user HR'
            ], 422));
        }

        $company = Company::find($companyId);
        if (!$company) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company tidak ditemukan'
            ], 404));
        }

        return $company;
    }

    // ==========================
    // SETTINGS (ambil dari companies table)
    // ==========================
    public function settings()
    {
        $this->ensureHr();

        $company = $this->companyOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => [
                'company_id' => (int) $company->id,
                'name' => $company->name,
                'latitude' => (string) $company->latitude,
                'longitude' => (string) $company->longitude,
                'radius_km' => (string) $company->radius_km,
                'time_in' => (string) $company->time_in,
                'time_out' => (string) $company->time_out,
            ]
        ], 200);
    }

    // ==========================
    // SETTINGS (update companies table)
    // ==========================
    public function updateSettings(Request $request)
    {
        $this->ensureHr();

        $company = $this->companyOrFail();

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            // radius_km di tabel kamu string, tapi kita validasi numeric
            'radius_km' => 'required|numeric|min:0.01|max:50',
            // optional: time_in/out
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
        ]);

        $company->latitude = (string) $request->latitude;
        $company->longitude = (string) $request->longitude;
        $company->radius_km = (string) $request->radius_km;

        if ($request->filled('time_in')) $company->time_in = $request->time_in;
        if ($request->filled('time_out')) $company->time_out = $request->time_out;

        $company->save();

        return response()->json([
            'status' => true,
            'message' => 'Setting attendance company berhasil diperbarui',
            'data' => [
                'company_id' => (int) $company->id,
                'latitude' => (string) $company->latitude,
                'longitude' => (string) $company->longitude,
                'radius_km' => (string) $company->radius_km,
                'time_in' => (string) $company->time_in,
                'time_out' => (string) $company->time_out,
            ]
        ], 200);
    }

    // =====================
    // LIST EMPLOYEE TODAY (untuk face scan)
    // =====================
    public function employeesToday()
    {
        $this->ensureHr();

        $company = $this->companyOrFail();
        $today = Carbon::today();

        $employees = User::query()
            ->where('role', 'employee')
            ->where('company_id', $company->id)
            ->select(['id', 'name', 'face_embedding'])
            ->with(['attendances' => function ($q) use ($today) {
                $q->whereDate('date', $today)
                    // attendance yang di-mark (marked_by != null) diprioritaskan
                    ->orderByDesc('marked_by')
                    ->orderByDesc('id');
            }])
            ->get()
            ->map(function ($u) {
                $attendance = $u->attendances->first();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'face_embedding' => $u->face_embedding,
                    'status' => $attendance->status ?? 'absent',
                    'marked_by' => $attendance->marked_by ?? null,
                    'time_in' => $attendance->time_in ?? null,
                    'time_out' => $attendance?->time_out,
                ];
            });

        return response()->json([
            'status' => true,
            'date' => $today->toDateString(),
            'company' => [
                'id' => (int) $company->id,
                'name' => $company->name,
                'latitude' => (string) $company->latitude,
                'longitude' => (string) $company->longitude,
                'radius_km' => (string) $company->radius_km,
            ],
            'data' => $employees
        ], 200);
    }

    // =====================
    // MARK EMPLOYEE (klik HR seperti ustadz klik santri)
    // =====================
    public function markEmployeeAttendance(Request $request)
    {
        $this->ensureHr();

        $company = $this->companyOrFail();

        $request->validate([
            'employee_id' => 'required|exists:users,id',
            'action' => 'required|in:checkin,checkout',
        ]);

        // pastikan user yang diabsenkan adalah employee company ini
        $employee = User::where('id', $request->employee_id)
            ->where('role', 'employee')
            ->where('company_id', $company->id)
            ->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'User bukan employee / bukan bagian company ini'
            ], 422);
        }

        $today = Carbon::today();

        // Ambil attendance hari ini (kalau belum ada, buat object kosong)
        $attendance = Attendance::firstOrNew([
            'user_id' => $employee->id,
            'date' => $today,
        ]);

        // Saat input via HR device
        $attendance->marked_by = auth()->id();

        if ($request->action === 'checkin') {
            if ($attendance->time_in) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee sudah check-in hari ini'
                ], 422);
            }

            $attendance->time_in = Carbon::now()->format('H:i:s');
            $attendance->latlon_in = '-';
            // status optional: on_time/late, kalau mau pakai jam company
            $attendance->status = 'on_time';
            $attendance->save();

            return response()->json([
                'status' => true,
                'message' => 'Check-in employee berhasil',
                'data' => [
                    'employee_id' => (int) $employee->id,
                    'action' => 'checkin',
                    'date' => $today->toDateString(),
                    'marked_by' => auth()->id(),
                    'attendance_id' => $attendance->id,
                    'time_in' => $attendance->time_in,
                ]
            ], 200);
        }

        // action === checkout
        if (!$attendance->time_in) {
            return response()->json([
                'status' => false,
                'message' => 'Employee belum check-in hari ini'
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status' => false,
                'message' => 'Employee sudah check-out hari ini'
            ], 422);
        }

        $attendance->time_out = Carbon::now()->format('H:i:s');
        $attendance->latlon_out = '-';
        $attendance->save();

        return response()->json([
            'status' => true,
            'message' => 'Check-out employee berhasil',
            'data' => [
                'employee_id' => (int) $employee->id,
                'action' => 'checkout',
                'date' => $today->toDateString(),
                'marked_by' => auth()->id(),
                'attendance_id' => $attendance->id,
                'time_out' => $attendance->time_out,
            ]
        ], 200);
    }

    // =====================
    // EMPLOYEE HISTORY (30 record)
    // =====================
    public function employeeHistory($id)
    {
        $this->ensureHr();

        $company = $this->companyOrFail();

        $employee = User::where('id', $id)
            ->where('role', 'employee')
            ->where('company_id', $company->id)
            ->first();

        if (!$employee) {
            return response()->json([
                'status' => false,
                'message' => 'Employee tidak ditemukan'
            ], 404);
        }

        $history = Attendance::where('user_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'status' => true,
            'employee' => [
                'id' => (int) $employee->id,
                'name' => $employee->name,
            ],
            'data' => $history
        ], 200);
    }
}
