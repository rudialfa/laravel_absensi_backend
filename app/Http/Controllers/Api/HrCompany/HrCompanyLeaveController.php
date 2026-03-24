<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Leaves;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrCompanyLeaveController extends Controller
{
    // public function index(Request $request)
    // {
    //     $user = $request->user();

    //     $q = Leaves::query()
    //         ->where('company_id', $user->company_id)
    //         ->with(['user:id,name,department,position', 'approver:id,name'])
    //         ->orderByDesc('start_date')
    //         ->orderByDesc('id');

    //     if ($request->filled('status')) $q->where('status', $request->status);
    //     if ($request->filled('user_id')) $q->where('user_id', (int)$request->user_id);
    //     if ($request->filled('from')) $q->whereDate('start_date', '>=', $request->from);
    //     if ($request->filled('to')) $q->whereDate('end_date', '<=', $request->to);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List leaves (HR)',
    //         'data' => $q->paginate((int)($request->get('per_page', 10))),
    //     ]);
    // }

    // public function show(Request $request, int $id)
    // {
    //     $user = $request->user();

    //     $data = Leaves::query()
    //         ->where('company_id', $user->company_id)
    //         ->with(['user:id,name,department,position', 'approver:id,name'])
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Leave detail (HR)',
    //         'data' => $data,
    //     ]);
    // }

    // public function approve(Request $request, int $id)
    // {
    //     $hr = $request->user();

    //     $v = Validator::make($request->all(), [
    //         'approval_note' => ['nullable', 'string'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $v->errors()->first(),
    //             'errors' => $v->errors(),
    //         ], 422);
    //     }

    //     $leave = Leaves::query()
    //         ->where('company_id', $hr->company_id)
    //         ->findOrFail($id);

    //     if ($leave->status !== 'pending') {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Hanya cuti pending yang bisa di-approve.',
    //         ], 422);
    //     }

    //     $leave->update([
    //         'status' => 'approved',
    //         'approved_by' => $hr->id,
    //         'approved_at' => now(),
    //         'approval_note' => $request->approval_note,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Leave approved',
    //         'data' => $leave,
    //     ]);
    // }

    // public function reject(Request $request, int $id)
    // {
    //     $hr = $request->user();

    //     $v = Validator::make($request->all(), [
    //         'approval_note' => ['nullable', 'string'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $v->errors()->first(),
    //             'errors' => $v->errors(),
    //         ], 422);
    //     }

    //     $leave = Leaves::query()
    //         ->where('company_id', $hr->company_id)
    //         ->findOrFail($id);

    //     if ($leave->status !== 'pending') {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Hanya cuti pending yang bisa di-reject.',
    //         ], 422);
    //     }

    //     $leave->update([
    //         'status' => 'rejected',
    //         'approved_by' => $hr->id,
    //         'approved_at' => now(),
    //         'approval_note' => $request->approval_note,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Leave rejected',
    //         'data' => $leave,
    //     ]);
    // }

    // kode 2
    // ============================================================
    // GET /api/company/hr/leaves
    // ============================================================
    public function index(Request $request)
    {
        $user = $request->user();
        $q    = Leaves::query()
            ->where('company_id', $user->company_id)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('user_id')) $q->where('user_id', (int)$request->user_id);
        if ($request->filled('from'))    $q->whereDate('start_date', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('end_date', '<=', $request->to);

        return response()->json([
            'status'  => true,
            'message' => 'List leaves (HR)',
            'data'    => $q->paginate((int)($request->get('per_page', 10))),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/leaves/{id}
    // ============================================================
    public function show(Request $request, int $id)
    {
        $data = Leaves::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Leave detail (HR)',
            'data'    => $data,
        ]);
    }

    // ============================================================
    // POST /api/company/hr/leaves/{id}/approve
    // ============================================================
    public function approve(Request $request, int $id)
    {
        $hr = $request->user();
        $v  = Validator::make($request->all(), [
            'approval_note' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()->first()], 422);
        }

        $leave = Leaves::query()
            ->where('company_id', $hr->company_id)
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['status' => false, 'message' => 'Hanya cuti pending yang bisa di-approve.'], 422);
        }

        $leave->update([
            'status'        => 'approved',
            'approved_by'   => $hr->id,
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json(['status' => true, 'message' => 'Leave approved', 'data' => $leave]);
    }

    // ============================================================
    // POST /api/company/hr/leaves/{id}/reject
    // ============================================================
    public function reject(Request $request, int $id)
    {
        $hr = $request->user();
        $v  = Validator::make($request->all(), [
            'approval_note' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()->first()], 422);
        }

        $leave = Leaves::query()
            ->where('company_id', $hr->company_id)
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['status' => false, 'message' => 'Hanya cuti pending yang bisa di-reject.'], 422);
        }

        $leave->update([
            'status'        => 'rejected',
            'approved_by'   => $hr->id,
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json(['status' => true, 'message' => 'Leave rejected', 'data' => $leave]);
    }

    // ============================================================
    // EXPORT PDF — rekap cuti karyawan
    // GET /api/company/hr/leaves/export
    //
    // Query params:
    //   status  (optional) — pending|approved|rejected
    //   from    (optional) — yyyy-MM-dd
    //   to      (optional) — yyyy-MM-dd
    //   month   (optional) — 1-12
    //   year    (optional)
    //
    // Install: composer require barryvdh/laravel-dompdf
    // ============================================================
    public function export(Request $request)
    {
        $user = $request->user();

        $q = Leaves::query()
            ->where('company_id', $user->company_id)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('user_id')) $q->where('user_id', (int)$request->user_id);
        if ($request->filled('from'))    $q->whereDate('start_date', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('end_date', '<=', $request->to);

        if ($request->filled('month') && $request->filled('year')) {
            $q->whereMonth('start_date', $request->month)
                ->whereYear('start_date', $request->year);
        } elseif ($request->filled('year')) {
            $q->whereYear('start_date', $request->year);
        }

        $leaves = $q->get();

        $stats = [
            'total'    => $leaves->count(),
            'pending'  => $leaves->where('status', 'pending')->count(),
            'approved' => $leaves->where('status', 'approved')->count(),
            'rejected' => $leaves->where('status', 'rejected')->count(),
        ];

        $bulanLabel = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $month       = $request->month;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? ($bulanLabel[$month] ?? $month) . ' ' . $year
            : ($request->filled('from') && $request->filled('to')
                ? $request->from . ' s/d ' . $request->to
                : 'Semua Periode');

        $fileName = 'leaves-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_leave', [
            'company'     => $user->company ?? (object)['name' => ''],
            'leaves'      => $leaves,
            'stats'       => $stats,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
