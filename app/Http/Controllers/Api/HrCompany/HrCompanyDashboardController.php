<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Loan;
use App\Models\Permission;
use App\Models\Payrool;
use Carbon\Carbon;


class HrCompanyDashboardController extends Controller
{
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus HR)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;

        if (!$companyId) {
            abort(response()->json([
                'status' => false,
                'message' => 'Company ID tidak ditemukan',
            ], 422));
        }

        return (int) $companyId;
    }

    /**
     * Summary stats untuk dashboard HR:
     * - total employee
     * - hadir hari ini
     * - permission pending
     * - loan pending
     * - payroll draft
     */
    public function summaryStats(Request $request)
    {
        $this->ensureHr();

        $companyId = $this->companyId();

        // pakai timezone app (pastikan config/app.php timezone Asia/Jakarta)
        $today = Carbon::today()->toDateString();

        // 1) total employee
        $totalEmployee = User::query()
            ->where('company_id', $companyId)
            ->where('role', 'employee')
            ->count();

        // 2) hadir hari ini
        // Asumsi: tabel attendances ada company_id, user_id, check_in / check_in_at / tanggal.
        // Aku buat versi paling umum: hitung yang check_in tidak null & tanggal hari ini.
        // ✅ Sesuaikan nama kolom check_in kamu kalau beda.
        $hadirHariIni = Attendance::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', $today)
            ->whereNotNull('check_in') // jika kolom kamu check_in_at, ganti di sini
            ->count();

        // 3) permission pending (is_approved NULL)
        $permissionPending = Permission::query()
            ->where('company_id', $companyId)
            ->whereNull('is_approved')
            ->count();

        // 4) loan pending
        // Asumsi: loans.status = pending|approved|rejected|paid
        $loanPending = Loan::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->count();

        // 5) payroll draft
        // Asumsi: payrools.status = draft|approved|paid
        $payrollDraft = Payrool::query()
            ->where('company_id', $companyId)
            ->where('status', 'draft')
            ->count();

        return response()->json([
            'status' => true,
            'message' => 'Summary stats HR',
            'data' => [
                'total_employee' => $totalEmployee,
                'hadir_hari_ini' => $hadirHariIni,
                'permission_pending' => $permissionPending,
                'loan_pending' => $loanPending,
                'payroll_draft' => $payrollDraft,
                'date' => $today,
            ],
        ]);
    }
}
