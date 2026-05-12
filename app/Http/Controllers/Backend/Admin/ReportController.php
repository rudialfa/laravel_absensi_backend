<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Payrool;
use App\Models\Loan;
use App\Models\User;
use App\Models\Company;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // Filter
        $filter_user     = $request->user_id;
        $filter_company  = $request->company_id;
        $filter_date     = $request->date;

        //tab
        $tab = $request->tab ?? 'absensi';

        // Query Absensi
        $absensi = Attendance::with('user')
            ->when($filter_user, fn($q) => $q->where('user_id', $filter_user))
            ->when($filter_date, fn($q) => $q->where('date', $filter_date))
            ->when(
                $filter_company,
                fn($q) =>
                $q->whereHas('user', fn($u) => $u->where('company_id', $filter_company))
            )
            ->orderBy('date', 'DESC')
            ->paginate(20);

        // // Permissions
        // $permissions = Permission::with('user')->latest()->paginate(20);

        // // Payroll
        // $payrolls = Payrool::with('user')->latest()->paginate(20);

        // // Loans
        // $loans = Loan::with('user')->latest()->paginate(20);

        //code 2
        // ================= PERMISSION =================
        $permissions = Permission::with('user')
            ->when($tab == 'permissions', function ($q) use ($filter_user, $filter_date, $filter_company) {
                $q->when($filter_user, fn($q) => $q->where('user_id', $filter_user))
                    ->when($filter_date, fn($q) => $q->where('date', $filter_date))
                    ->when(
                        $filter_company,
                        fn($q) =>
                        $q->whereHas('user', fn($u) => $u->where('company_id', $filter_company))
                    );
            })
            ->latest()
            ->paginate(20);

        // ================= PAYROLL =================
        $payrolls = Payrool::with('user')
            ->when($tab == 'payroll', function ($q) use ($filter_user, $filter_date, $filter_company) {
                $q->when($filter_user, fn($q) => $q->where('user_id', $filter_user))
                    ->when($filter_date, fn($q) => $q->whereDate('created_at', $filter_date))
                    ->when(
                        $filter_company,
                        fn($q) =>
                        $q->whereHas('user', fn($u) => $u->where('company_id', $filter_company))
                    );
            })
            ->latest()
            ->paginate(20);

        // ================= LOAN =================
        $loans = Loan::with('user')
            ->when($tab == 'loan', function ($q) use ($filter_user, $filter_date, $filter_company) {
                $q->when($filter_user, fn($q) => $q->where('user_id', $filter_user))
                    ->when($filter_date, fn($q) => $q->whereDate('created_at', $filter_date))
                    ->when(
                        $filter_company,
                        fn($q) =>
                        $q->whereHas('user', fn($u) => $u->where('company_id', $filter_company))
                    );
            })
            ->latest()
            ->paginate(20);


        return view('pages.admin.report.index', [
            'absensi'      => $absensi,
            'permissions'  => $permissions,
            'payrolls'     => $payrolls,
            'loans'        => $loans,
            'tab'          => $tab,

            // Filter data
            'users'        => User::where('role', 'user')->get(),
            'companies'    => Company::all(),
        ]);
    }
}
