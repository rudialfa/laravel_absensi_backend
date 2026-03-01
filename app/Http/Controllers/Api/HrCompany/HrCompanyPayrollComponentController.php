<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\PayrollComponent;
use App\Models\Payrool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HrCompanyPayrollComponentController extends Controller
{
    private function resolvePayroll(int $payrollId): Payrool
    {
        $hr = Auth::user();

        if (!in_array($hr->role, ['hr', 'superadmin'])) {
            abort(403, 'Akses ditolak. Hanya HR atau Superadmin yang dapat mengakses fitur ini.');
        }

        return Payrool::whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($payrollId);
    }

    private function recalculateNetPay(Payrool $payroll): void
    {
        $totalAddition  = $payroll->components()->where('type', 'addition')->sum('amount');
        $totalDeduction = $payroll->components()->where('type', 'deduction')->sum('amount');

        $net = $payroll->base_salary
            + $payroll->overtime_pay
            + $payroll->bonus
            + $totalAddition
            - $totalDeduction;

        $payroll->update([
            'allowance'  => $totalAddition,
            'deductions' => $totalDeduction,
            'net_pay'    => max(0, $net),
        ]);
    }

    // =========================================================================
    // INDEX
    // =========================================================================
    public function index(int $payrollId): JsonResponse
    {
        $payroll    = $this->resolvePayroll($payrollId);
        $components = $payroll->components()->orderBy('type')->orderBy('name')->get();

        $additions  = $components->where('type', 'addition')->values();
        $deductions = $components->where('type', 'deduction')->values();

        return response()->json([
            'success' => true,
            'message' => 'Komponen payroll berhasil diambil.',
            'data'    => [
                'payroll_id'      => $payroll->id,
                'additions'       => $additions,
                'deductions'      => $deductions,
                'total_addition'  => $additions->sum('amount'),
                'total_deduction' => $deductions->sum('amount'),
                'current_net_pay' => $payroll->net_pay,
            ],
        ]);
    }

    // =========================================================================
    // STORE (satu komponen)
    // =========================================================================
    public function store(Request $request, int $payrollId): JsonResponse
    {
        $payroll = $this->resolvePayroll($payrollId);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Komponen hanya bisa ditambahkan pada payroll berstatus draft.',
            ], 422);
        }

        $validated = $request->validate([
            'name'   => 'required|string|max:100',
            'type'   => ['required', Rule::in(['addition', 'deduction'])],
            'amount' => 'required|numeric|min:0',
            'note'   => 'nullable|string|max:255',
        ]);

        $component = $payroll->components()->create($validated);
        $this->recalculateNetPay($payroll->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Komponen berhasil ditambahkan.',
            'data'    => [
                'component'   => $component,
                'new_net_pay' => $payroll->fresh()->net_pay,
            ],
        ], 201);
    }

    // =========================================================================
    // STORE BULK (banyak komponen sekaligus)
    // =========================================================================
    public function storeBulk(Request $request, int $payrollId): JsonResponse
    {
        $payroll = $this->resolvePayroll($payrollId);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Komponen hanya bisa ditambahkan pada payroll berstatus draft.',
            ], 422);
        }

        $validated = $request->validate([
            'components'          => 'required|array|min:1|max:50',
            'components.*.name'   => 'required|string|max:100',
            'components.*.type'   => ['required', Rule::in(['addition', 'deduction'])],
            'components.*.amount' => 'required|numeric|min:0',
            'components.*.note'   => 'nullable|string|max:255',
        ]);

        $now        = now();
        $insertData = array_map(function ($item) use ($payroll, $now) {
            return [
                'payroll_id' => $payroll->id,
                'name'       => $item['name'],
                'type'       => $item['type'],
                'amount'     => $item['amount'],
                'note'       => $item['note'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $validated['components']);

        PayrollComponent::insert($insertData);
        $this->recalculateNetPay($payroll->fresh());

        return response()->json([
            'success' => true,
            'message' => count($insertData) . ' komponen berhasil ditambahkan.',
            'data'    => [
                'total_inserted' => count($insertData),
                'new_net_pay'    => $payroll->fresh()->net_pay,
            ],
        ], 201);
    }

    // =========================================================================
    // UPDATE (satu komponen)
    // =========================================================================
    public function update(Request $request, int $payrollId, int $componentId): JsonResponse
    {
        $payroll = $this->resolvePayroll($payrollId);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Komponen hanya bisa diubah pada payroll berstatus draft.',
            ], 422);
        }

        $component = PayrollComponent::where('payroll_id', $payrollId)->findOrFail($componentId);

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:100',
            'type'   => ['sometimes', Rule::in(['addition', 'deduction'])],
            'amount' => 'sometimes|numeric|min:0',
            'note'   => 'nullable|string|max:255',
        ]);

        $component->update($validated);
        $this->recalculateNetPay($payroll->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Komponen berhasil diupdate.',
            'data'    => [
                'component'   => $component->fresh(),
                'new_net_pay' => $payroll->fresh()->net_pay,
            ],
        ]);
    }

    // =========================================================================
    // DESTROY (satu komponen)
    // =========================================================================
    public function destroy(int $payrollId, int $componentId): JsonResponse
    {
        $payroll = $this->resolvePayroll($payrollId);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Komponen hanya bisa dihapus pada payroll berstatus draft.',
            ], 422);
        }

        $component = PayrollComponent::where('payroll_id', $payrollId)->findOrFail($componentId);
        $component->delete();

        $this->recalculateNetPay($payroll->fresh());

        return response()->json([
            'success' => true,
            'message' => 'Komponen berhasil dihapus.',
            'data'    => [
                'new_net_pay' => $payroll->fresh()->net_pay,
            ],
        ]);
    }
}
