<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Permission;
use Carbon\Carbon;


class HrCompanyDashboardController extends Controller
{
    private function ensureHr()
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $today = Carbon::today()->toDateString();

        $totalEmployees = User::where('company_id', $companyId)->where('role', 'employee')->count();

        $todayAttendances = Attendance::where('company_id', $companyId)
            ->whereDate('date', $today);

        $checkedIn = (clone $todayAttendances)->whereNotNull('check_in')->count();
        $checkedOut = (clone $todayAttendances)->whereNotNull('check_out')->count();

        $pendingPermissions = Permission::where('company_id', $companyId)
            ->where('is_approved', false)
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Dashboard HR',
            'data' => [
                'date' => $today,
                'total_employees' => $totalEmployees,
                'checked_in' => $checkedIn,
                'checked_out' => $checkedOut,
                'pending_permissions' => $pendingPermissions,
            ]
        ]);
    }
}
