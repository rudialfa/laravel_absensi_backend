<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Payrool;

class HrCompanyPayrollController extends Controller
{

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json(['status' => false, 'message' => 'Company ID tidak ditemukan'], 422));
        }
        return (int) $companyId;
    }

    public function index(Request $request)
    {
        $this->ensureHr();

        $q = Payrool::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->orderByDesc('id');

        if ($request->filled('status')) $q->where('status', $request->status); // draft|approved|paid
        if ($request->filled('user_id')) $q->where('user_id', (int)$request->user_id);

        return response()->json([
            'status' => true,
            'message' => 'List payrolls',
            'data' => $q->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureHr();

        $validated = $request->validate([
            'user_id' => 'required|integer',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
            'base_salary' => 'nullable|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'overtime_pay' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
        ]);

        $base = (float)($validated['base_salary'] ?? 0);
        $allow = (float)($validated['allowance'] ?? 0);
        $ded = (float)($validated['deductions'] ?? 0);
        $ot = (float)($validated['overtime_pay'] ?? 0);
        $bonus = (float)($validated['bonus'] ?? 0);

        $net = ($base + $allow + $ot + $bonus) - $ded;

        $payroll = Payrool::create([
            'company_id' => $this->companyId(),
            'user_id' => (int)$validated['user_id'],
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'base_salary' => $base,
            'allowance' => $allow,
            'deductions' => $ded,
            'overtime_pay' => $ot,
            'bonus' => $bonus,
            'net_pay' => $net,
            'status' => 'draft',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Payroll dibuat',
            'data' => $payroll->fresh('user'),
        ], 201);
    }

    public function show($id)
    {
        $this->ensureHr();

        $payroll = Payrool::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail payroll',
            'data' => $payroll,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $payroll = Payrool::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $validated = $request->validate([
            'period_start' => 'sometimes|required|date',
            'period_end' => 'sometimes|required|date',
            'base_salary' => 'sometimes|nullable|numeric|min:0',
            'allowance' => 'sometimes|nullable|numeric|min:0',
            'deductions' => 'sometimes|nullable|numeric|min:0',
            'overtime_pay' => 'sometimes|nullable|numeric|min:0',
            'bonus' => 'sometimes|nullable|numeric|min:0',
        ]);

        $payroll->fill($validated);

        // hitung ulang net_pay jika ada perubahan komponen
        $base = (float)($payroll->base_salary ?? 0);
        $allow = (float)($payroll->allowance ?? 0);
        $ded = (float)($payroll->deductions ?? 0);
        $ot = (float)($payroll->overtime_pay ?? 0);
        $bonus = (float)($payroll->bonus ?? 0);

        $payroll->net_pay = ($base + $allow + $ot + $bonus) - $ded;
        $payroll->save();

        return response()->json([
            'status' => true,
            'message' => 'Payroll diupdate',
            'data' => $payroll->fresh('user'),
        ]);
    }

    public function approve($id)
    {
        $this->ensureHr();

        $payroll = Payrool::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $payroll->status = 'approved';
        $payroll->save();

        return response()->json([
            'status' => true,
            'message' => 'Payroll disetujui',
            'data' => $payroll->fresh('user'),
        ]);
    }

    public function markPaid($id)
    {
        $this->ensureHr();

        $payroll = Payrool::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $payroll->status = 'paid';
        $payroll->save();

        return response()->json([
            'status' => true,
            'message' => 'Payroll ditandai dibayar',
            'data' => $payroll->fresh('user'),
        ]);
    }
}
