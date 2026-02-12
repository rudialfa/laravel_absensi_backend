<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Loan;

class HrCompanyLoanController extends Controller
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

        $q = Loan::with('user')->where('company_id', $companyId);

        if ($request->filled('status')) $q->where('status', $request->status);

        return response()->json([
            'status' => true,
            'message' => 'List loans',
            'data' => $q->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $loan = Loan::with('user')->where('company_id', $companyId)->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail loan', 'data' => $loan]);
    }

    public function approve($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $loan = Loan::where('company_id', $companyId)->findOrFail($id);
        $loan->status = 'approved';
        $loan->save();

        return response()->json(['status' => true, 'message' => 'Loan disetujui', 'data' => $loan]);
    }

    public function reject($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $loan = Loan::where('company_id', $companyId)->findOrFail($id);
        $loan->status = 'rejected';
        $loan->save();

        return response()->json(['status' => true, 'message' => 'Loan ditolak', 'data' => $loan]);
    }

    public function markPaid($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $loan = Loan::where('company_id', $companyId)->findOrFail($id);
        $loan->status = 'paid';
        $loan->save();

        return response()->json(['status' => true, 'message' => 'Loan ditandai lunas', 'data' => $loan]);
    }
}
