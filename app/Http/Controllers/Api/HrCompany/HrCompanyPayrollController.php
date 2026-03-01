<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Loan;
use App\Models\OvertimeRequest;
use App\Models\Payrool;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrCompanyPayrollController extends Controller
{

    // kode 2
    private function authorizeHR(): User
    {
        $user = Auth::user();

        if (!in_array($user->role, ['hr', 'superadmin'])) {
            abort(403, 'Akses ditolak. Hanya HR atau Superadmin yang dapat mengakses fitur ini.');
        }

        return $user;
    }

    // =========================================================================
    // INDEX
    // =========================================================================
    public function index(Request $request): JsonResponse
    {
        $hr = $this->authorizeHR();

        $request->validate([
            'period_start' => 'nullable|date',
            'period_end'   => 'nullable|date|after_or_equal:period_start',
            'status'       => ['nullable', Rule::in(['draft', 'approved', 'paid'])],
            'user_id'      => 'nullable|integer|exists:users,id',
            'search'       => 'nullable|string|max:100',
            'per_page'     => 'nullable|integer|min:5|max:100',
        ]);

        $query = Payrool::with(['user:id,name,email,position,department,salary,company_id'])
            ->whereHas('user', fn($q) => $q->where('company_id', $hr->company_id));

        if ($request->filled('period_start')) {
            $query->where('period_start', '>=', $request->period_start);
        }
        if ($request->filled('period_end')) {
            $query->where('period_end', '<=', $request->period_end);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('search')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', '%' . $request->search . '%'));
        }

        $payrolls = $query->orderByDesc('period_start')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Daftar payroll berhasil diambil.',
            'data'    => $payrolls,
        ]);
    }

    // =========================================================================
    // SHOW
    // =========================================================================
    public function show(int $id): JsonResponse
    {
        $hr = $this->authorizeHR();

        $payroll = Payrool::with([
            'user:id,name,email,position,department,salary,company_id',
            'components' => fn($q) => $q->orderBy('type')->orderBy('name'),
        ])
            ->whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($id);

        $additions  = $payroll->components->where('type', 'addition')->values();
        $deductions = $payroll->components->where('type', 'deduction')->values();

        return response()->json([
            'success' => true,
            'message' => 'Detail payroll berhasil diambil.',
            'data'    => [
                'payroll'         => $payroll->makeHidden('components'),
                'additions'       => $additions,
                'deductions'      => $deductions,
                'total_addition'  => $additions->sum('amount'),
                'total_deduction' => $deductions->sum('amount'),
            ],
        ]);
    }

    // =========================================================================
    // STORE
    // =========================================================================
    public function store(Request $request): JsonResponse
    {
        $hr = $this->authorizeHR();

        $validated = $request->validate([
            'user_id'      => 'required|integer|exists:users,id',
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
            'bonus'        => 'nullable|numeric|min:0',
            'overtime_pay' => 'nullable|numeric|min:0',
        ]);

        $employee = User::where('id', $validated['user_id'])
            ->where('company_id', $hr->company_id)
            ->firstOrFail();

        $exists = Payrool::where('user_id', $validated['user_id'])
            ->where('period_start', $validated['period_start'])
            ->where('period_end', $validated['period_end'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll untuk karyawan ini pada periode tersebut sudah ada.',
            ], 422);
        }

        $baseSalary  = $employee->salary ?? 0;
        $overtimePay = $validated['overtime_pay'] ?? 0;
        $bonus       = $validated['bonus'] ?? 0;
        $net         = max(0, $baseSalary + $overtimePay + $bonus);

        $payroll = Payrool::create([
            'user_id'      => $employee->id,
            'period_start' => $validated['period_start'],
            'period_end'   => $validated['period_end'],
            'base_salary'  => $baseSalary,
            'allowance'    => 0,
            'deductions'   => 0,
            'overtime_pay' => $overtimePay,
            'bonus'        => $bonus,
            'net_pay'      => $net,
            'status'       => 'draft',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payroll berhasil dibuat.',
            'data'    => $payroll->load('user:id,name,email,position,department,salary'),
        ], 201);
    }

    // =========================================================================
    // UPDATE (hanya bonus & overtime, hanya draft)
    // =========================================================================
    public function update(Request $request, int $id): JsonResponse
    {
        $hr = $this->authorizeHR();

        $payroll = Payrool::whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($id);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Payroll yang sudah di-approve atau dibayar tidak dapat diubah.',
            ], 422);
        }

        $validated = $request->validate([
            'bonus'        => 'sometimes|numeric|min:0',
            'overtime_pay' => 'sometimes|numeric|min:0',
        ]);

        $payroll->fill($validated);
        $payroll->net_pay = max(
            0,
            $payroll->base_salary
                + $payroll->overtime_pay
                + $payroll->bonus
                + $payroll->allowance
                - $payroll->deductions
        );
        $payroll->save();

        return response()->json([
            'success' => true,
            'message' => 'Payroll berhasil diupdate.',
            'data'    => $payroll->load('user:id,name,email,position,department,salary'),
        ]);
    }

    // =========================================================================
    // DESTROY (hanya draft)
    // =========================================================================
    public function destroy(int $id): JsonResponse
    {
        $hr = $this->authorizeHR();

        $payroll = Payrool::whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($id);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Payroll yang sudah di-approve atau dibayar tidak dapat dihapus.',
            ], 422);
        }

        $payroll->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payroll beserta semua komponennya berhasil dihapus.',
        ]);
    }

    // =========================================================================
    // APPROVE (draft → approved)
    // =========================================================================
    public function approve(int $id): JsonResponse
    {
        $hr = $this->authorizeHR();

        $payroll = Payrool::whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($id);

        if ($payroll->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya payroll berstatus draft yang dapat di-approve.',
            ], 422);
        }

        $payroll->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'message' => 'Payroll berhasil di-approve.',
            'data'    => $payroll->load('user:id,name,email,position,department,salary'),
        ]);
    }

    // =========================================================================
    // MARK AS PAID (approved → paid)
    // =========================================================================
    public function markAsPaid(int $id): JsonResponse
    {
        $hr = $this->authorizeHR();

        $payroll = Payrool::whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($id);

        if ($payroll->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya payroll berstatus approved yang dapat ditandai sebagai paid.',
            ], 422);
        }

        $payroll->update(['status' => 'paid']);

        return response()->json([
            'success' => true,
            'message' => 'Payroll berhasil ditandai sebagai paid.',
            'data'    => $payroll->load('user:id,name,email,position,department,salary'),
        ]);
    }

    // =========================================================================
    // GENERATE (otomatis semua karyawan)
    // =========================================================================
    public function generate(Request $request): JsonResponse
    {
        $hr = $this->authorizeHR();

        $validated = $request->validate([
            'period_start'             => 'required|date',
            'period_end'               => 'required|date|after_or_equal:period_start',
            'overtime_rate_per_minute' => 'nullable|numeric|min:0',
        ]);

        $overtimeRate = $validated['overtime_rate_per_minute'] ?? 0;

        $employees = User::where('company_id', $hr->company_id)
            ->whereNotIn('role', ['hr', 'superadmin', 'COMPANY'])
            ->get();

        $generated = [];
        $skipped   = [];

        DB::transaction(function () use ($employees, $validated, $overtimeRate, &$generated, &$skipped) {
            foreach ($employees as $employee) {
                $exists = Payrool::where('user_id', $employee->id)
                    ->where('period_start', $validated['period_start'])
                    ->where('period_end', $validated['period_end'])
                    ->exists();

                if ($exists) {
                    $skipped[] = [
                        'id'     => $employee->id,
                        'name'   => $employee->name,
                        'reason' => 'Sudah ada payroll untuk periode ini',
                    ];
                    continue;
                }

                $baseSalary = $employee->salary ?? 0;

                $overtimeMinutes = OvertimeRequest::where('user_id', $employee->id)
                    ->where('status', 'approved')
                    ->whereBetween('date', [$validated['period_start'], $validated['period_end']])
                    ->sum('minutes');

                $overtimePay = $overtimeMinutes * $overtimeRate;

                $loanDeduction = Loan::where('user_id', $employee->id)
                    ->where('status', 'approved')
                    ->where('balance', '>', 0)
                    ->get()
                    ->sum(fn($loan) => $loan->installments > 0 ? ($loan->amount / $loan->installments) : 0);

                $net = max(0, $baseSalary + $overtimePay - $loanDeduction);

                $payroll = Payrool::create([
                    'user_id'      => $employee->id,
                    'period_start' => $validated['period_start'],
                    'period_end'   => $validated['period_end'],
                    'base_salary'  => $baseSalary,
                    'allowance'    => 0,
                    'deductions'   => $loanDeduction,
                    'overtime_pay' => $overtimePay,
                    'bonus'        => 0,
                    'net_pay'      => $net,
                    'status'       => 'draft',
                ]);

                $generated[] = [
                    'payroll_id'   => $payroll->id,
                    'user_id'      => $employee->id,
                    'name'         => $employee->name,
                    'base_salary'  => $baseSalary,
                    'overtime_pay' => $overtimePay,
                    'deductions'   => $loanDeduction,
                    'net_pay'      => $net,
                ];
            }
        });

        return response()->json([
            'success'   => true,
            'message'   => 'Generate payroll selesai.',
            'generated' => $generated,
            'skipped'   => $skipped,
            'summary'   => [
                'total_generated' => count($generated),
                'total_skipped'   => count($skipped),
                'total_net_pay'   => collect($generated)->sum('net_pay'),
            ],
        ], 201);
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================
    public function summary(Request $request): JsonResponse
    {
        $hr = $this->authorizeHR();

        $request->validate([
            'period_start' => 'required|date',
            'period_end'   => 'required|date|after_or_equal:period_start',
        ]);

        $baseQuery = Payrool::whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->where('period_start', '>=', $request->period_start)
            ->where('period_end', '<=', $request->period_end);

        $summary = (clone $baseQuery)->select(
            'status',
            DB::raw('COUNT(*) as total_employees'),
            DB::raw('SUM(base_salary) as total_base_salary'),
            DB::raw('SUM(allowance) as total_allowance'),
            DB::raw('SUM(deductions) as total_deductions'),
            DB::raw('SUM(overtime_pay) as total_overtime_pay'),
            DB::raw('SUM(bonus) as total_bonus'),
            DB::raw('SUM(net_pay) as total_net_pay')
        )->groupBy('status')->get();

        $grandTotal = (clone $baseQuery)->sum('net_pay');

        return response()->json([
            'success' => true,
            'message' => 'Ringkasan payroll berhasil diambil.',
            'data'    => [
                'period'              => ['start' => $request->period_start, 'end' => $request->period_end],
                'by_status'           => $summary,
                'grand_total_net_pay' => $grandTotal,
            ],
        ]);
    }

    // =========================================================================
    // SLIP
    // =========================================================================
    public function slip(int $id): JsonResponse
    {
        $hr = $this->authorizeHR();

        $payroll = Payrool::with([
            'user:id,name,email,position,department,salary,company_id,phone,image_url',
            'components' => fn($q) => $q->orderBy('type')->orderBy('name'),
        ])
            ->whereHas('user', fn($q) => $q->where('company_id', $hr->company_id))
            ->findOrFail($id);

        $workingDays = $this->countWorkingDays($payroll->period_start, $payroll->period_end);

        $presentDays = Attendance::where('user_id', $payroll->user_id)
            ->whereBetween('date', [$payroll->period_start, $payroll->period_end])
            ->whereIn('status', ['on_time', 'late', 'overtime'])
            ->count();

        $additions  = $payroll->components->where('type', 'addition')->values();
        $deductions = $payroll->components->where('type', 'deduction')->values();

        return response()->json([
            'success' => true,
            'message' => 'Data slip gaji berhasil diambil.',
            'data'    => [
                'payroll'         => $payroll->makeHidden('components'),
                'additions'       => $additions,
                'deductions'      => $deductions,
                'total_addition'  => $additions->sum('amount'),
                'total_deduction' => $deductions->sum('amount'),
                'attendance'      => [
                    'working_days' => $workingDays,
                    'present_days' => $presentDays,
                    'absent_days'  => max(0, $workingDays - $presentDays),
                ],
            ],
        ]);
    }

    // =========================================================================
    // PRIVATE HELPER
    // =========================================================================
    private function countWorkingDays(string $start, string $end): int
    {
        $startDate = Carbon::parse($start);
        $endDate   = Carbon::parse($end);
        $days      = 0;

        while ($startDate->lte($endDate)) {
            if ($startDate->isWeekday()) {
                $days++;
            }
            $startDate->addDay();
        }

        return $days;
    }
}
