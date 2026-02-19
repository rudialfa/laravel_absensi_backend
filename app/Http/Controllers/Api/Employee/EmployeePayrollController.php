<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payrool;
use Illuminate\Support\Facades\Schema;

class EmployeePayrollController extends Controller
{
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus employee)',
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
     * GET /company/employee/payrolls?status=draft|approved|paid&from=YYYY-MM-DD&to=YYYY-MM-DD&page=1
     */
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $q = Payrool::query()
            ->where('user_id', auth()->id());

        // kalau kolom company_id sudah ada, aktifkan filter company
        if (Schema::hasColumn('payrools', 'company_id')) {
            $q->where('company_id', $this->companyId());
        }

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        // filter periode payroll
        if ($request->filled('from')) {
            $q->whereDate('period_start', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('period_end', '<=', $request->to);
        }

        $data = $q->orderByDesc('id')->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'List payroll saya',
            'data'    => $data,
        ]);
    }

    /**
     * GET /company/employee/payrolls/{id}
     */
    public function show($id)
    {
        $this->ensureEmployee();

        $q = Payrool::query()
            ->where('user_id', auth()->id());

        if (Schema::hasColumn('payrools', 'company_id')) {
            $q->where('company_id', $this->companyId());
        }

        $payroll = $q->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail payroll',
            'data'    => $payroll,
        ]);
    }
}
