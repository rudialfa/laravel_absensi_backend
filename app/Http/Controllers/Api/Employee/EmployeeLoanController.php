<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployeeLoanController extends Controller
{

    // kode 2
    public function index(Request $request)
    {
        $query = Loan::where('user_id', Auth::id())
            ->where('company_id', Auth::user()->company_id)
            ->with(['approvedBy:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $loans = $query->get()->map(function ($loan) {
            return [
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
                // Progress biar langsung bisa ditampilkan di list
                'progress_percent'      => $loan->amount > 0
                    ? round((($loan->amount - $loan->balance) / $loan->amount) * 100, 1)
                    : 0,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $loans,
        ]);
    }

    /**
     * GET /api/company/employee/loans/{id}
     * Employee lihat detail pinjaman miliknya + histori bayar + progress
     * Guard: user_id harus milik Auth::id()
     */
    public function show($id)
    {
        $loan = Loan::where('user_id', Auth::id())
            ->where('company_id', Auth::user()->company_id)
            ->with([
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
                'summary'  => [
                    'total_paid'       => $payments->sum('amount_paid'),
                    'total_payments'   => $payments->count(),
                    'remaining'        => $loan->balance,
                    'progress_percent' => $loan->amount > 0
                        ? round((($loan->amount - $loan->balance) / $loan->amount) * 100, 1)
                        : 0,
                ],
                'payments' => $payments->map(function ($p) {
                    return [
                        'id'              => $p->id,
                        'amount_paid'     => $p->amount_paid,
                        'amount_expected' => $p->amount_expected,
                        'balance_after'   => $p->balance_after,
                        'payment_date'    => $p->payment_date,
                        'method'          => $p->method,
                        'note'            => $p->note,
                        'recorded_by'     => $p->recordedBy,
                        'created_at'      => $p->created_at,
                    ];
                }),
            ],
        ]);
    }

    /**
     * POST /api/company/employee/loans
     * Employee ajukan pinjaman sendiri (self-service)
     * Status default: pending (menunggu persetujuan HR)
     *
     * Body:
     * {
     *   "amount": 2000000,
     *   "installments": 10,
     *   "purpose_category": "education",
     *   "purpose_note": "Bayar SPP anak",            // opsional
     *   "payment_type": "scheduled_date",
     *   "payment_date_of_month": 5,                  // wajib jika scheduled_date
     *   "attachment": "https://..."                  // opsional, URL file/foto bukti
     * }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'                => 'required|numeric|min:10000',
            'installments'          => 'required|integer|min:1|max:60',
            'purpose_category'      => 'required|string|in:education,health,emergency,renovation,business,other',
            'purpose_note'          => 'nullable|string|max:500',
            'payment_type'          => 'required|in:salary_deduction,scheduled_date,lump_sum',
            'payment_date_of_month' => 'required_if:payment_type,scheduled_date|nullable|integer|min:1|max:28',
            'attachment'            => 'nullable|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Cek apakah masih ada pinjaman pending atau aktif
        $activeLoan = Loan::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($activeLoan) {
            return response()->json([
                'success' => false,
                'message' => 'Kamu masih memiliki pinjaman yang sedang berjalan atau menunggu persetujuan. '
                    . 'Selesaikan pinjaman sebelumnya sebelum mengajukan yang baru.',
            ], 422);
        }

        // Hitung monthly_installment otomatis (bisa di-override HR saat approve)
        $monthlyInstallment = round((float) $request->amount / (int) $request->installments, 2);

        $loan = Loan::create([
            'company_id'            => Auth::user()->company_id,
            'user_id'               => Auth::id(),
            'amount'                => $request->amount,
            'balance'               => $request->amount, // sisa = full amount saat pengajuan
            'installments'          => $request->installments,
            'monthly_installment'   => $monthlyInstallment,
            'purpose_category'      => $request->purpose_category,
            'purpose_note'          => $request->purpose_note,
            'payment_type'          => $request->payment_type,
            'payment_date_of_month' => $request->payment_date_of_month,
            'status'                => 'pending',
            'attachment'            => $request->attachment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pinjaman berhasil dikirim. Menunggu persetujuan HR.',
            'data'    => $loan,
        ], 201);
    }

    /**
     * PUT /api/company/employee/loans/{id}/cancel
     * Employee batalkan pengajuan pinjaman miliknya sendiri
     * Hanya bisa cancel jika status masih PENDING
     *
     * Body:
     * {
     *   "reason": "Sudah tidak butuh"   // opsional
     * }
     */
    public function cancel(Request $request, $id)
    {
        $loan = Loan::where('user_id', Auth::id())
            ->where('company_id', Auth::user()->company_id)
            ->where('status', 'pending') // hanya bisa cancel saat pending
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $loan->update([
            'status'        => 'canceled',
            'approval_note' => $request->reason ?? 'Dibatalkan oleh karyawan.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan pinjaman berhasil dibatalkan.',
        ]);
    }

    /**
     * GET /api/company/employee/loans/{id}/payments
     * Employee lihat histori pembayaran cicilan miliknya
     */
    public function paymentHistory($id)
    {
        // Pastikan loan milik employee yang login
        $loan = Loan::where('user_id', Auth::id())
            ->where('company_id', Auth::user()->company_id)
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
                    'amount'              => $loan->amount,
                    'balance'             => $loan->balance,
                    'monthly_installment' => $loan->monthly_installment,
                    'payment_type'        => $loan->payment_type,
                    'status'              => $loan->status,
                ],
                'summary'  => [
                    'total_paid'       => $payments->sum('amount_paid'),
                    'total_expected'   => $payments->sum('amount_expected'),
                    'total_diff'       => round(
                        $payments->sum('amount_paid') - $payments->sum('amount_expected'),
                        2
                    ),
                    'total_payments'   => $payments->count(),
                    'progress_percent' => $loan->amount > 0
                        ? round((($loan->amount - $loan->balance) / $loan->amount) * 100, 1)
                        : 0,
                ],
                'payments' => $payments->map(function ($p) {
                    return [
                        'id'              => $p->id,
                        'amount_paid'     => $p->amount_paid,
                        'amount_expected' => $p->amount_expected,
                        'balance_after'   => $p->balance_after,
                        'payment_date'    => $p->payment_date,
                        'method'          => $p->method,
                        'note'            => $p->note,
                        'recorded_by'     => $p->recordedBy,
                        'created_at'      => $p->created_at,
                    ];
                }),
            ],
        ]);
    }

    /**
     * GET /api/company/employee/loans/active
     * Employee lihat pinjaman aktif saat ini (shortcut untuk dashboard)
     * Mengembalikan 1 loan aktif + progress ringkas
     */
    public function active()
    {
        $loan = Loan::where('user_id', Auth::id())
            ->where('company_id', Auth::user()->company_id)
            ->whereIn('status', ['pending', 'active'])
            ->with('approvedBy:id,name')
            ->latest()
            ->first();

        if (!$loan) {
            return response()->json([
                'success' => true,
                'data'    => null,
                'message' => 'Tidak ada pinjaman aktif.',
            ]);
        }

        $totalPaid = LoanPayment::where('loan_id', $loan->id)->sum('amount_paid');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                    => $loan->id,
                'amount'                => $loan->amount,
                'balance'               => $loan->balance,
                'installments'          => $loan->installments,
                'monthly_installment'   => $loan->monthly_installment,
                'purpose_category'      => $loan->purpose_category,
                'payment_type'          => $loan->payment_type,
                'payment_date_of_month' => $loan->payment_date_of_month,
                'status'                => $loan->status,
                'approved_by'           => $loan->approvedBy,
                'approval_note'         => $loan->approval_note,
                'approved_at'           => $loan->approved_at,
                'created_at'            => $loan->created_at,
                'summary'               => [
                    'total_paid'       => $totalPaid,
                    'remaining'        => $loan->balance,
                    'progress_percent' => $loan->amount > 0
                        ? round((($loan->amount - $loan->balance) / $loan->amount) * 100, 1)
                        : 0,
                ],
            ],
        ]);
    }
}
