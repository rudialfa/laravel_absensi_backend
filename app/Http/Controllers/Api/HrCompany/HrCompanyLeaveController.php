<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Leaves;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrCompanyLeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $q = Leaves::query()
            ->where('company_id', $user->company_id)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('user_id')) $q->where('user_id', (int)$request->user_id);
        if ($request->filled('from')) $q->whereDate('start_date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('end_date', '<=', $request->to);

        return response()->json([
            'status' => true,
            'message' => 'List leaves (HR)',
            'data' => $q->paginate((int)($request->get('per_page', 10))),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $data = Leaves::query()
            ->where('company_id', $user->company_id)
            ->with(['user:id,name,department,position', 'approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Leave detail (HR)',
            'data' => $data,
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $hr = $request->user();

        $v = Validator::make($request->all(), [
            'approval_note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => $v->errors()->first(),
                'errors' => $v->errors(),
            ], 422);
        }

        $leave = Leaves::query()
            ->where('company_id', $hr->company_id)
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Hanya cuti pending yang bisa di-approve.',
            ], 422);
        }

        $leave->update([
            'status' => 'approved',
            'approved_by' => $hr->id,
            'approved_at' => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave approved',
            'data' => $leave,
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $hr = $request->user();

        $v = Validator::make($request->all(), [
            'approval_note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => $v->errors()->first(),
                'errors' => $v->errors(),
            ], 422);
        }

        $leave = Leaves::query()
            ->where('company_id', $hr->company_id)
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Hanya cuti pending yang bisa di-reject.',
            ], 422);
        }

        $leave->update([
            'status' => 'rejected',
            'approved_by' => $hr->id,
            'approved_at' => now(),
            'approval_note' => $request->approval_note,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave rejected',
            'data' => $leave,
        ]);
    }
}
