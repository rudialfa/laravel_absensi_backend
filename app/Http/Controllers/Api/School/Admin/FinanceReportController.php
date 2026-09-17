<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentBill;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    /**
     * GET /api/school/admin/finance-report?bill_type_id=&period_month=&period_year=
     * Rekap total tertagih, total terbayar, total piutang.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'bill_type_id'  => 'nullable|exists:bill_types,id',
            'period_month'  => 'nullable|integer',
            'period_year'   => 'nullable|integer',
        ]);

        $bills = StudentBill::where('company_id', $request->user()->company_id)
            ->when($data['bill_type_id'] ?? null, fn($q, $id) => $q->where('bill_type_id', $id))
            ->when($data['period_month'] ?? null, fn($q, $m) => $q->where('period_month', $m))
            ->when($data['period_year'] ?? null, fn($q, $y) => $q->where('period_year', $y))
            ->with('payments')
            ->get();

        $totalTertagih = $bills->sum('amount');
        $totalTerbayar = $bills->sum(fn($b) => $b->total_paid);
        $totalPiutang = $totalTertagih - $totalTerbayar;

        $piutangList = $bills->where('status', '!=', 'lunas')
            ->load('student:id,name')
            ->map(fn($b) => [
                'student_name' => $b->student->name,
                'title' => $b->title,
                'sisa' => $b->remaining_amount,
                'due_date' => $b->due_date,
            ])
            ->values();

        return response()->json([
            'status'  => true,
            'message' => 'Rekap keuangan berhasil diambil',
            'data'    => [
                'total_tertagih' => $totalTertagih,
                'total_terbayar' => $totalTerbayar,
                'total_piutang'  => $totalPiutang,
                'daftar_piutang' => $piutangList,
            ],
        ]);
    }
}
