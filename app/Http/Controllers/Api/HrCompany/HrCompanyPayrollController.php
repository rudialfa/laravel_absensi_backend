<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Payrool;

class HrCompanyPayrollController extends Controller
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

        $q = Payrool::with('user')->where('company_id', $companyId);

        if ($request->filled('month')) $q->where('month', $request->month);

        return response()->json([
            'status' => true,
            'message' => 'List payroll',
            'data' => $q->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $validated = $request->validate([
            'user_id' => 'required|integer',
            'month' => 'required|string|max:7', // "YYYY-MM"
            'base_salary' => 'required|numeric',
            'bonus' => 'nullable|numeric',
            'deduction' => 'nullable|numeric',
        ]);

        $payroll = Payrool::create([
            'company_id' => $companyId,
            'user_id' => $validated['user_id'],
            'month' => $validated['month'],
            'base_salary' => $validated['base_salary'],
            'bonus' => $validated['bonus'] ?? 0,
            'deduction' => $validated['deduction'] ?? 0,
            'status' => 'draft', // draft/approved/paid
        ]);

        return response()->json(['status' => true, 'message' => 'Payroll dibuat', 'data' => $payroll], 201);
    }

    public function show($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $payroll = Payrool::with('user')->where('company_id', $companyId)->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail payroll', 'data' => $payroll]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $payroll = Payrool::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'base_salary' => 'sometimes|required|numeric',
            'bonus' => 'nullable|numeric',
            'deduction' => 'nullable|numeric',
        ]);

        foreach (['base_salary', 'bonus', 'deduction'] as $f) {
            if (array_key_exists($f, $validated)) $payroll->$f = $validated[$f];
        }

        $payroll->save();

        return response()->json(['status' => true, 'message' => 'Payroll diupdate', 'data' => $payroll]);
    }

    public function approve($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $payroll = Payrool::where('company_id', $companyId)->findOrFail($id);
        $payroll->status = 'approved';
        $payroll->save();

        return response()->json(['status' => true, 'message' => 'Payroll disetujui', 'data' => $payroll]);
    }

    public function markPaid($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $payroll = Payrool::where('company_id', $companyId)->findOrFail($id);
        $payroll->status = 'paid';

        // kalau kolom paid_at ada:
        if (isset($payroll->paid_at)) {
            $payroll->paid_at = Carbon::now();
        }

        $payroll->save();

        return response()->json(['status' => true, 'message' => 'Payroll ditandai dibayar', 'data' => $payroll]);
    }
}
