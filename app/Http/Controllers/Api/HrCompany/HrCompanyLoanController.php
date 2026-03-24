<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HrCompanyLoanController extends Controller
{

    // ============================================================
    //  HR — SUMMARY DASHBOARD
    // ============================================================

    /**
     * GET /api/hr/loans/summary
     * Ringkasan semua pinjaman di perusahaan
     */
    public function summary()
    {
        $companyId = Auth::user()->company_id;

        return response()->json([
            'success' => true,
            'data'    => [
                'total_pending'   => Loan::where('company_id', $companyId)->where('status', 'pending')->count(),
                'total_active'    => Loan::where('company_id', $companyId)->where('status', 'active')->count(),
                'total_paid'      => Loan::where('company_id', $companyId)->where('status', 'paid')->count(),
                'total_rejected'  => Loan::where('company_id', $companyId)->where('status', 'rejected')->count(),
                'total_canceled'  => Loan::where('company_id', $companyId)->where('status', 'canceled')->count(),
                // Total sisa hutang semua loan yang masih active
                'total_balance'   => Loan::where('company_id', $companyId)->where('status', 'active')->sum('balance'),
                // Total pernah dicairkan
                'total_disbursed' => Loan::where('company_id', $companyId)
                    ->whereIn('status', ['active', 'paid'])
                    ->sum('amount'),
            ],
        ]);
    }

    // ============================================================
    //  HR — KELOLA PINJAMAN
    // ============================================================

    /**
     * GET /api/hr/loans
     * Lihat semua pinjaman di perusahaan
     *
     * Query params (semua opsional):
     * ?status=pending
     * ?user_id=5
     * ?payment_type=scheduled_date
     */
    public function index(Request $request)
    {
        $query = Loan::where('company_id', Auth::user()->company_id)
            ->with(['user:id,name,position,department', 'approvedBy:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        $loans = $query->get()->map(function ($loan) {
            return [
                'id'                    => $loan->id,
                'user'                  => $loan->user,
                'amount'                => $loan->amount,
                'balance'               => $loan->balance,
                'installments'          => $loan->installments,
                'monthly_installment'   => $loan->monthly_installment,
                'purpose_category'      => $loan->purpose_category,
                'purpose_note'          => $loan->purpose_note,
                'payment_type'          => $loan->payment_type,
                'payment_date_of_month' => $loan->payment_date_of_month,
                'status'                => $loan->status,
                'attachment'            => $loan->attachment,
                'approved_by'           => $loan->approvedBy,
                'approval_note'         => $loan->approval_note,
                'approved_at'           => $loan->approved_at,
                'created_at'            => $loan->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $loans,
        ]);
    }

    /**
     * GET /api/hr/loans/{id}
     * Detail pinjaman + info karyawan + histori pembayaran + progress
     */
    public function show($id)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)
            ->with([
                'user:id,name,position,department,salary',
                'approvedBy:id,name',
                'payments.recordedBy:id,name',
            ])
            ->findOrFail($id);

        $payments = $loan->payments->sortByDesc('payment_date')->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'loan'     => [
                    'id'                    => $loan->id,
                    'amount'                => $loan->amount,
                    'balance'               => $loan->balance,
                    'installments'          => $loan->installments,
                    'monthly_installment'   => $loan->monthly_installment,
                    'purpose_category'      => $loan->purpose_category,
                    'purpose_note'          => $loan->purpose_note,
                    'payment_type'          => $loan->payment_type,
                    'payment_date_of_month' => $loan->payment_date_of_month,
                    'status'                => $loan->status,
                    'attachment'            => $loan->attachment,
                    'approved_by'           => $loan->approvedBy,
                    'approval_note'         => $loan->approval_note,
                    'approved_at'           => $loan->approved_at,
                    'created_at'            => $loan->created_at,
                ],
                'user'     => $loan->user,
                'summary'  => [
                    'total_paid'       => $payments->sum('amount_paid'),
                    'total_payments'   => $payments->count(),
                    'remaining'        => $loan->balance,
                    'progress_percent' => $loan->amount > 0
                        ? round((($loan->amount - $loan->balance) / $loan->amount) * 100, 1)
                        : 0,
                ],
                'payments' => $payments,
            ],
        ]);
    }

    /**
     * POST /api/hr/loans
     * HR membuat pinjaman langsung untuk employee
     * (dipakai selama fitur self-service employee belum aktif)
     *
     * Body:
     * {
     *   "user_id": 5,
     *   "amount": 1000000,
     *   "installments": 10,
     *   "purpose_category": "education",
     *   "purpose_note": "Bayar SPP anak",        // opsional
     *   "payment_type": "scheduled_date",
     *   "payment_date_of_month": 3,              // wajib jika scheduled_date
     *   "monthly_installment": 100000,           // opsional, auto-hitung jika kosong
     *   "approval_note": "Disetujui langsung",   // opsional
     *   "attachment": "https://..."              // opsional
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id'               => 'required|exists:users,id',
            'amount'                => 'required|numeric|min:10000',
            'installments'          => 'required|integer|min:1|max:60',
            'purpose_category'      => 'required|string|in:education,health,emergency,renovation,business,other',
            'purpose_note'          => 'nullable|string|max:500',
            'payment_type'          => 'required|in:salary_deduction,scheduled_date,lump_sum',
            'payment_date_of_month' => 'required_if:payment_type,scheduled_date|nullable|integer|min:1|max:28',
            'monthly_installment'   => 'nullable|numeric|min:1000',
            'approval_note'         => 'nullable|string|max:500',
            'attachment'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Pastikan employee milik company yang sama
        $employee = User::where('id', $request->user_id)
            ->where('company_id', Auth::user()->company_id)
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan tidak ditemukan.',
            ], 404);
        }

        // Cek apakah masih ada pinjaman aktif
        $activeLoan = Loan::where('user_id', $request->user_id)
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($activeLoan) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan masih memiliki pinjaman yang belum lunas.',
            ], 422);
        }

        $installments = $request->payment_type === 'lump_sum' ? 1 : $request->installments;
        $monthly      = $request->filled('monthly_installment')
            ? $request->monthly_installment
            : round($request->amount / $installments, 2);

        $loan = Loan::create([
            'company_id'            => Auth::user()->company_id,
            'user_id'               => $request->user_id,
            'amount'                => $request->amount,
            'balance'               => $request->amount,
            'installments'          => $installments,
            'monthly_installment'   => $monthly,
            'purpose_category'      => $request->purpose_category,
            'purpose_note'          => $request->purpose_note,
            'payment_type'          => $request->payment_type,
            'payment_date_of_month' => $request->payment_type === 'scheduled_date'
                ? $request->payment_date_of_month
                : null,
            'status'                => 'active', // HR buat langsung → langsung active
            'approved_by'           => Auth::id(),
            'approved_at'           => now(),
            'approval_note'         => $request->approval_note,
            'attachment'            => $request->attachment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil dibuat dan langsung aktif.',
            'data'    => $loan->load('user:id,name,position,department'),
        ], 201);
    }

    /**
     * PUT /api/hr/loans/{id}/approve
     * Setujui pinjaman yang diajukan employee (pending → active)
     *
     * Body (semua opsional):
     * {
     *   "monthly_installment": 80000,   // override cicilan jika ada negosiasi
     *   "approval_note": "Disetujui"
     * }
     */
    public function approve(Request $request, $id)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'monthly_installment' => 'nullable|numeric|min:1000',
            'approval_note'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $loan->update([
            'status'              => 'active',
            'approved_by'         => Auth::id(),
            'approved_at'         => now(),
            'approval_note'       => $request->approval_note,
            'monthly_installment' => $request->filled('monthly_installment')
                ? $request->monthly_installment
                : $loan->monthly_installment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil disetujui.',
            'data'    => $loan->fresh()->load('user:id,name'),
        ]);
    }

    /**
     * PUT /api/hr/loans/{id}/reject
     * Tolak pinjaman (pending → rejected)
     *
     * Body:
     * {
     *   "approval_note": "Alasan penolakan"   // WAJIB
     * }
     */
    public function reject(Request $request, $id)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'approval_note' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $loan->update([
            'status'        => 'rejected',
            'approved_by'   => Auth::id(),
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil ditolak.',
        ]);
    }

    /**
     * PUT /api/hr/loans/{id}/cancel
     * HR batalkan pinjaman (pending atau active → canceled)
     * Contoh: karyawan resign, atau salah input
     *
     * Body:
     * {
     *   "approval_note": "Alasan pembatalan"   // WAJIB
     * }
     */
    public function cancel(Request $request, $id)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)
            ->whereIn('status', ['pending', 'active'])
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'approval_note' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $loan->update([
            'status'        => 'canceled',
            'approval_note' => $request->approval_note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pinjaman berhasil dibatalkan.',
        ]);
    }

    // ============================================================
    //  HR — KELOLA PEMBAYARAN
    // ============================================================

    /**
     * GET /api/hr/loans/{id}/payments
     * Histori pembayaran lengkap untuk satu pinjaman
     */
    public function paymentHistory($id)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)
            ->with('user:id,name')
            ->findOrFail($id);

        $payments = LoanPayment::where('loan_id', $id)
            ->with('recordedBy:id,name')
            ->orderByDesc('payment_date')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'loan'     => [
                    'id'                  => $loan->id,
                    'user'                => $loan->user,
                    'amount'              => $loan->amount,
                    'balance'             => $loan->balance,
                    'monthly_installment' => $loan->monthly_installment,
                    'payment_type'        => $loan->payment_type,
                    'status'              => $loan->status,
                ],
                'summary'  => [
                    'total_paid'       => $payments->sum('amount_paid'),
                    'total_expected'   => $payments->sum('amount_expected'),
                    // negatif = ada bulan yang kurang bayar (partial)
                    'total_diff'       => round($payments->sum('amount_paid') - $payments->sum('amount_expected'), 2),
                    'total_payments'   => $payments->count(),
                    'progress_percent' => $loan->amount > 0
                        ? round((($loan->amount - $loan->balance) / $loan->amount) * 100, 1)
                        : 0,
                ],
                'payments' => $payments,
            ],
        ]);
    }

    /**
     * POST /api/hr/loans/{id}/payments
     * HR input pembayaran cicilan
     * Partial payment diperbolehkan (bayar < monthly_installment = OK)
     *
     * Body:
     * {
     *   "amount_paid": 50000,           // boleh partial, boleh lebih
     *   "payment_date": "2025-03-03",
     *   "method": "manual",             // manual | lump_sum
     *   "note": "Transfer BCA"          // opsional
     * }
     */
    public function recordPayment(Request $request, $id)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)
            ->where('status', 'active')
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'amount_paid'  => 'required|numeric|min:1000',
            'payment_date' => 'required|date',
            'method'       => 'required|in:manual,lump_sum',
            'note'         => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Tidak boleh bayar melebihi sisa hutang
        $amountPaid   = min((float) $request->amount_paid, (float) $loan->balance);
        $balanceAfter = round((float) $loan->balance - $amountPaid, 2);

        $payment = LoanPayment::create([
            'loan_id'         => $loan->id,
            'user_id'         => $loan->user_id,
            'company_id'      => $loan->company_id,
            'amount_expected' => $loan->monthly_installment,
            'amount_paid'     => $amountPaid,
            'balance_after'   => $balanceAfter,
            'payment_date'    => $request->payment_date,
            'method'          => $request->method,
            'payroll_id'      => null, // akan diisi otomatis nanti saat modul payroll aktif
            'note'            => $request->note,
            'recorded_by'     => Auth::id(),
        ]);

        // Update balance & cek lunas
        $newStatus = $balanceAfter <= 0 ? 'paid' : 'active';
        $loan->update([
            'balance' => $balanceAfter,
            'status'  => $newStatus,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newStatus === 'paid'
                ? 'Pembayaran berhasil. Pinjaman sudah LUNAS!'
                : 'Pembayaran berhasil dicatat.',
            'data'    => [
                'payment'       => $payment->load('recordedBy:id,name'),
                'loan_status'   => $newStatus,
                'balance_after' => $balanceAfter,
            ],
        ], 201);
    }

    /**
     * DELETE /api/hr/loans/{id}/payments/{paymentId}
     * HR hapus record pembayaran jika salah input
     * Otomatis kembalikan balance ke kondisi sebelumnya
     */
    public function deletePayment($id, $paymentId)
    {
        $loan = Loan::where('company_id', Auth::user()->company_id)->findOrFail($id);

        $payment = LoanPayment::where('loan_id', $id)->findOrFail($paymentId);

        // Kembalikan balance
        $restoredBalance = round((float) $loan->balance + (float) $payment->amount_paid, 2);

        $loan->update([
            'balance' => $restoredBalance,
            // Jika sebelumnya paid karena pembayaran ini, kembalikan ke active
            'status'  => $loan->status === 'paid' ? 'active' : $loan->status,
        ]);

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Record pembayaran berhasil dihapus. Balance telah dikembalikan.',
            'data'    => [
                'balance_restored' => $restoredBalance,
            ],
        ]);
    }

    // export
    public function export(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $query = Loan::where('company_id', $companyId)
            ->with(['user:id,name,position,department', 'approvedBy:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status'))  $query->where('status', $request->status);
        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);

        $loans = $query->get();

        // Summary stats
        $stats = [
            'total'          => $loans->count(),
            'pending'        => $loans->where('status', 'pending')->count(),
            'active'         => $loans->where('status', 'active')->count(),
            'paid'           => $loans->where('status', 'paid')->count(),
            'rejected'       => $loans->where('status', 'rejected')->count(),
            'canceled'       => $loans->where('status', 'canceled')->count(),
            'total_balance'  => $loans->where('status', 'active')->sum('balance'),
            'total_disbursed' => $loans->whereIn('status', ['active', 'paid'])->sum('amount'),
        ];

        $statusLabel = $request->filled('status') ? ' - ' . ucfirst($request->status) : '';
        $fileName    = 'loans-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_loan', [
            'company'     => Auth::user()->company ?? (object)['name' => ''],
            'loans'       => $loans,
            'stats'       => $stats,
            'statusLabel' => $statusLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
