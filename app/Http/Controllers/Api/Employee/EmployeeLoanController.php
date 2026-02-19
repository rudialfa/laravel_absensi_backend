<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Loan;

class EmployeeLoanController extends Controller
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

    // =========================
    // LIST (SELF) paginate
    // =========================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $q = Loan::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id');

        // optional filter: status
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return response()->json([
            'status' => true,
            'message' => 'List loan saya',
            'data' => $q->paginate(20),
        ]);
    }

    // =========================
    // CREATE (AJUKAN PINJAMAN)
    // =========================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'installments' => 'nullable|integer|min:0',
            // kalau kamu nanti tambah kolom reason:
            // 'reason' => 'nullable|string|max:500',
        ]);

        $amount = (float) $validated['amount'];

        $loan = Loan::create([
            'company_id'   => $this->companyId(),
            'user_id'      => auth()->id(),
            'amount'       => $amount,
            'balance'      => $amount, // default: sisa = amount
            'installments' => (int)($validated['installments'] ?? 0),
            'status'       => 'pending',
            // 'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Pengajuan loan berhasil dibuat',
            'data' => $loan,
        ], 201);
    }

    // =========================
    // DETAIL (SELF)
    // =========================
    public function show($id)
    {
        $this->ensureEmployee();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail loan',
            'data' => $loan,
        ]);
    }

    // =========================
    // CANCEL (SELF) - optional
    // hanya boleh cancel kalau masih pending
    // =========================
    public function cancel($id)
    {
        $this->ensureEmployee();

        $loan = Loan::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if ($loan->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Tidak bisa cancel, loan sudah diproses',
            ], 422);
        }

        $loan->delete();

        return response()->json([
            'status' => true,
            'message' => 'Loan berhasil dibatalkan',
        ]);
    }
}
