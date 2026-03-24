<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class HrCompanyOvertimeRequestController extends Controller
{

    public function index(Request $request)
    {
        $hr = $request->user();

        $q = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('user_id')) $q->where('user_id', (int) $request->user_id);
        if ($request->filled('from'))    $q->whereDate('date', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('date', '<=', $request->to);

        return response()->json([
            'status'  => true,
            'message' => 'List overtime requests (HR)',
            'data'    => $q->paginate((int) ($request->get('per_page', 10))),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/overtimes/{id}
    // ============================================================
    public function show(Request $request, int $id)
    {
        $hr = $request->user();

        $data = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->with(['user:id,name,department,position', 'attendance', 'approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Overtime request detail (HR)',
            'data'    => $data,
        ]);
    }

    // ============================================================
    // POST /api/company/hr/overtimes/{id}/approve
    // ============================================================
    public function approve(Request $request, int $id)
    {
        $hr = $request->user();

        $v = Validator::make($request->all(), [
            'approval_note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $v->errors()->first(),
                'errors'  => $v->errors(),
            ], 422);
        }

        return DB::transaction(function () use ($hr, $request, $id) {
            $overtime = OvertimeRequest::query()
                ->lockForUpdate()
                ->where('company_id', $hr->company_id)
                ->findOrFail($id);

            if ($overtime->status !== 'pending') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Hanya request pending yang bisa di-approve.',
                ], 422);
            }

            $overtime->update([
                'status'        => 'approved',
                'approved_by'   => $hr->id,
                'approved_at'   => now(),
                'approval_note' => $request->approval_note,
            ]);

            $attendance = null;

            if ($overtime->attendance_id) {
                $attendance = Attendance::query()
                    ->where('id', $overtime->attendance_id)
                    ->when(Schema::hasColumn('attendances', 'company_id'), function ($q) use ($hr) {
                        $q->where('company_id', $hr->company_id);
                    })
                    ->first();
            }

            if (!$attendance) {
                $attendance = Attendance::query()
                    ->where('user_id', $overtime->user_id)
                    ->whereDate('date', $overtime->date)
                    ->when(Schema::hasColumn('attendances', 'company_id'), function ($q) use ($hr) {
                        $q->where('company_id', $hr->company_id);
                    })
                    ->first();
            }

            if ($attendance) {
                $attendance->update([
                    'overtime_minutes'  => (int) $overtime->minutes,
                    'approved_overtime' => true,
                    'status'            => 'overtime',
                    'marked_by'         => $hr->id,
                ]);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Overtime request approved',
                'data'    => [
                    'overtime'            => $overtime,
                    'attendance_updated'  => (bool) $attendance,
                ],
            ]);
        });
    }

    // ============================================================
    // POST /api/company/hr/overtimes/{id}/reject
    // ============================================================
    public function reject(Request $request, int $id)
    {
        $hr = $request->user();

        $v = Validator::make($request->all(), [
            'approval_note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $v->errors()->first(),
                'errors'  => $v->errors(),
            ], 422);
        }

        $overtime = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->findOrFail($id);

        if ($overtime->status !== 'pending') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya request pending yang bisa di-reject.',
            ], 422);
        }

        $overtime->update([
            'status'        => 'rejected',
            'approved_by'   => $hr->id,
            'approved_at'   => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Overtime request rejected',
            'data'    => $overtime,
        ]);
    }

    // ============================================================
    // EXPORT PDF — rekap overtime per periode
    // GET /api/company/hr/overtimes/export
    //
    // Query params:
    //   month  (required) — 1-12
    //   year   (required)
    //   status (optional) — pending|approved|rejected
    //
    // Install: composer require barryvdh/laravel-dompdf
    // ============================================================
    public function export(Request $request)
    {
        $hr = $request->user();

        $validated = $request->validate([
            'month'  => 'required|integer|between:1,12',
            'year'   => 'required|integer|min:2020|max:2099',
            'status' => 'nullable|in:pending,approved,rejected',
        ]);

        $month  = (int) $validated['month'];
        $year   = (int) $validated['year'];
        $status = $validated['status'] ?? null;

        $q = OvertimeRequest::query()
            ->where('company_id', $hr->company_id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderBy('date')
            ->orderBy('user_id');

        if ($status) $q->where('status', $status);

        $overtimes = $q->get();

        // Summary counts
        $totalPending  = $overtimes->where('status', 'pending')->count();
        $totalApproved = $overtimes->where('status', 'approved')->count();
        $totalRejected = $overtimes->where('status', 'rejected')->count();
        $totalMinutes  = $overtimes->where('status', 'approved')->sum('minutes');

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

        $periodLabel = ($bulanLabel[$month] ?? $month) . ' ' . $year;
        $statusLabel = $status ? ' - ' . ucfirst($status) : '';
        $fileName    = 'overtime-' . $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_overtime', [
            'company'       => $hr->company ?? (object)['name' => ''],
            'periodLabel'   => $periodLabel . $statusLabel,
            'overtimes'     => $overtimes,
            'totalPending'  => $totalPending,
            'totalApproved' => $totalApproved,
            'totalRejected' => $totalRejected,
            'totalMinutes'  => $totalMinutes,
            'generatedAt'   => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
