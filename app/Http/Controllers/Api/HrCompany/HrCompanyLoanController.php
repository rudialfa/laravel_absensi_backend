<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Loan;

class HrCompanyLoanController extends Controller
{
    // private function ensureHr()
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'hr') {
    //         abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
    //     }
    // }

    // private function companyId()
    // {
    //     return auth()->user()->company_id ?? null;
    // }

    // public function index(Request $request)
    // {
    //     $this->ensureHr();
    //     $companyId = $this->companyId();

    //     $q = Loan::with('user')->where('company_id', $companyId);

    //     if ($request->filled('status')) $q->where('status', $request->status);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List loans',
    //         'data' => $q->orderByDesc('id')->paginate(20),
    //     ]);
    // }

    // public function show($id)
    // {
    //     $this->ensureHr();
    //     $companyId = $this->companyId();

    //     $loan = Loan::with('user')->where('company_id', $companyId)->findOrFail($id);

    //     return response()->json(['status' => true, 'message' => 'Detail loan', 'data' => $loan]);
    // }

    // public function approve($id)
    // {
    //     $this->ensureHr();
    //     $companyId = $this->companyId();

    //     $loan = Loan::where('company_id', $companyId)->findOrFail($id);
    //     $loan->status = 'approved';
    //     $loan->save();

    //     return response()->json(['status' => true, 'message' => 'Loan disetujui', 'data' => $loan]);
    // }

    // public function reject($id)
    // {
    //     $this->ensureHr();
    //     $companyId = $this->companyId();

    //     $loan = Loan::where('company_id', $companyId)->findOrFail($id);
    //     $loan->status = 'rejected';
    //     $loan->save();

    //     return response()->json(['status' => true, 'message' => 'Loan ditolak', 'data' => $loan]);
    // }

    // public function markPaid($id)
    // {
    //     $this->ensureHr();
    //     $companyId = $this->companyId();

    //     $loan = Loan::where('company_id', $companyId)->findOrFail($id);
    //     $loan->status = 'paid';
    //     $loan->save();

    //     return response()->json(['status' => true, 'message' => 'Loan ditandai lunas', 'data' => $loan]);
    // }

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus HR)',
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

        return $companyId;
    }

    // =========================
    // LIST (HR) - semua loan di company
    // filter: status, user_id
    // =========================
    public function index(Request $request)
    {
        $this->ensureHr();

        $q = Loan::query()
            ->where('company_id', $this->companyId())
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $q->where('user_id', $request->user_id);
        }

        return response()->json([
            'status' => true,
            'message' => 'List loan company',
            'data' => $q->paginate(20),
        ]);
    }

    // =========================
    // DETAIL
    // =========================
    public function show($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail loan',
            'data' => $loan,
        ]);
    }

    // =========================
    // APPROVE
    // =========================
    public function approve($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Loan tidak bisa di-approve karena status bukan pending',
            ], 422);
        }

        // saat approve, set balance awal = amount (kalau balance null)
        if (is_null($loan->balance)) {
            $loan->balance = $loan->amount;
        }

        $loan->status = 'approved';
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan berhasil di-approve',
            'data' => $loan,
        ]);
    }

    // =========================
    // REJECT
    // =========================
    public function reject(Request $request, $id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Loan tidak bisa di-reject karena status bukan pending',
            ], 422);
        }

        // optional: simpan alasan reject kalau kolomnya ada
        // $request->validate(['reason' => 'nullable|string|max:255']);
        // $loan->reject_reason = $request->reason;

        $loan->status = 'rejected';
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan berhasil di-reject',
            'data' => $loan,
        ]);
    }

    // =========================
    // MARK PAID
    // =========================
    public function markPaid($id)
    {
        $this->ensureHr();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        if (!in_array($loan->status, ['approved'], true)) {
            return response()->json([
                'status' => false,
                'message' => 'Loan tidak bisa ditandai paid karena status bukan approved',
            ], 422);
        }

        $loan->status = 'paid';
        $loan->balance = 0;
        $loan->save();

        return response()->json([
            'status' => true,
            'message' => 'Loan ditandai lunas',
            'data' => $loan,
        ]);
    }
}
