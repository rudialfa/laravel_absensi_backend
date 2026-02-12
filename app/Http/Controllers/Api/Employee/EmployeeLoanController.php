<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Loan;

class EmployeeLoanController extends Controller
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

    public function index()
    {
        $this->ensureEmployee();

        $data = Loan::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['status' => true, 'message' => 'List loan saya', 'data' => $data]);
    }

    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        $loan = Loan::create([
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json(['status' => true, 'message' => 'Pengajuan loan dibuat', 'data' => $loan], 201);
    }

    public function show($id)
    {
        $this->ensureEmployee();

        $loan = Loan::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail loan', 'data' => $loan]);
    }
}
