<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillPayment;
use App\Models\BillType;
use App\Models\Student;
use App\Models\StudentBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentBillController extends Controller
{
    /**
     * GET /api/school/admin/student-bills?class_id=&status=&bill_type_id=
     */
    public function index(Request $request)
    {
        $bills = StudentBill::where('company_id', $request->user()->company_id)
            ->with(['student:id,name,class_id', 'student.classRoom:id,name', 'billType:id,name'])
            ->when($request->query('class_id'), fn($q, $id) => $q->whereHas('student', fn($q2) => $q2->where('class_id', $id)))
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('bill_type_id'), fn($q, $id) => $q->where('bill_type_id', $id))
            ->latest('due_date')
            ->paginate(30);

        return response()->json(['status' => true, 'message' => 'Data tagihan berhasil diambil', 'data' => $bills]);
    }

    /**
     * POST /api/school/admin/student-bills/generate
     * Generate tagihan untuk banyak siswa sekaligus — misal SPP bulan
     * ini untuk seluruh siswa aktif (atau 1 kelas tertentu).
     *
     * Body:
     * {
     *   "bill_type_id": 1,
     *   "title": "SPP Januari 2027",
     *   "amount": 150000,
     *   "due_date": "2027-01-10",
     *   "period_month": 1, "period_year": 2027,
     *   "class_id": null,  // null = semua kelas
     *   "student_ids": null // atau spesifik siswa tertentu, override class_id
     * }
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'bill_type_id'   => 'required|exists:bill_types,id',
            'title'          => 'required|string|max:150',
            'amount'         => 'required|numeric|min:0',
            'due_date'       => 'required|date',
            'period_month'   => 'nullable|integer|min:1|max:12',
            'period_year'    => 'nullable|integer|min:2000',
            'class_id'       => 'nullable|exists:class_rooms,id',
            'student_ids'    => 'nullable|array',
            'student_ids.*'  => 'exists:students,id',
        ]);

        $companyId = $request->user()->company_id;

        $billType = BillType::findOrFail($data['bill_type_id']);
        abort_if($billType->company_id !== $companyId, 403);

        $studentsQuery = Student::where('company_id', $companyId)->where('is_active', true);

        if (!empty($data['student_ids'])) {
            $studentsQuery->whereIn('id', $data['student_ids']);
        } elseif (!empty($data['class_id'])) {
            $studentsQuery->where('class_id', $data['class_id']);
        }

        $students = $studentsQuery->get();

        $created = DB::transaction(function () use ($students, $data, $companyId, $request) {
            $result = [];
            foreach ($students as $student) {
                // firstOrCreate mencegah dobel kalau periode bulan/tahun sama
                $bill = StudentBill::firstOrCreate(
                    [
                        'student_id'    => $student->id,
                        'bill_type_id'  => $data['bill_type_id'],
                        'period_month'  => $data['period_month'] ?? null,
                        'period_year'   => $data['period_year'] ?? null,
                    ],
                    [
                        'company_id'  => $companyId,
                        'title'       => $data['title'],
                        'amount'      => $data['amount'],
                        'due_date'    => $data['due_date'],
                        'created_by'  => $request->user()->id,
                    ]
                );
                $result[] = $bill;
            }
            return $result;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Tagihan berhasil dibuat untuk ' . count($created) . ' siswa',
            'data'    => $created,
        ], 201);
    }

    /**
     * POST /api/school/admin/student-bills/{bill}/payments
     */
    public function recordPayment(Request $request, StudentBill $bill)
    {
        $this->authorize('manage', $bill);

        $data = $request->validate([
            'amount'  => 'required|numeric|min:1',
            'method'  => 'required|in:cash,transfer',
            'paid_at' => 'required|date',
            'notes'   => 'nullable|string',
        ]);

        abort_if($bill->status === 'lunas', 422, 'Tagihan ini sudah lunas');
        abort_if($data['amount'] > $bill->remaining_amount, 422, 'Jumlah bayar melebihi sisa tagihan (' . $bill->remaining_amount . ')');

        $payment = DB::transaction(function () use ($data, $bill, $request) {
            $receiptNumber = 'KWT-' . now()->year . '-' . str_pad((string) (BillPayment::count() + 1), 6, '0', STR_PAD_LEFT);

            $payment = BillPayment::create([
                'student_bill_id' => $bill->id,
                'amount'          => $data['amount'],
                'method'          => $data['method'],
                'paid_at'         => $data['paid_at'],
                'receipt_number'  => $receiptNumber,
                'notes'           => $data['notes'] ?? null,
                'recorded_by'     => $request->user()->id,
            ]);

            $bill->refreshStatus();

            return $payment;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Pembayaran berhasil dicatat',
            'data'    => $payment->load('recordedBy:id,name'),
        ], 201);
    }
}
