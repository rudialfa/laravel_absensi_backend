<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payrool;

class EmployeePayrollController extends Controller
{
    private function ensureEmployee()
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus employee)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureEmployee();

        $q = Payrool::where('company_id', $this->companyId())
            ->where('user_id', auth()->id());

        if ($request->filled('month')) $q->where('month', $request->month);

        return response()->json([
            'status' => true,
            'message' => 'List payroll saya',
            'data' => $q->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show($id)
    {
        $this->ensureEmployee();

        $payroll = Payrool::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail payroll', 'data' => $payroll]);
    }
}
