<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Loan;

class HrCompanyLoanController extends Controller
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

        $q = Loan::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status); // pending|approved|rejected|paid
        }

        return response()->json([
            'status' => true,
            'message' => 'List loans',
            'data' => $q->paginate(20),
        ]);
    }

    public function show($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail loan',
            'data' => $loan,
        ]);
    }

    public function approve($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $loan->status = 'approved';
        $loan->balance = $loan->amount; // default: balance = amount saat approve
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan disetujui',
            'data' => $loan->fresh('user'),
        ]);
    }

    public function reject($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $loan->status = 'rejected';
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan ditolak',
            'data' => $loan->fresh('user'),
        ]);
    }

    public function markPaid($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $loan->status = 'paid';
        $loan->balance = 0;
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan ditandai lunas',
            'data' => $loan->fresh('user'),
        ]);
    }
}
