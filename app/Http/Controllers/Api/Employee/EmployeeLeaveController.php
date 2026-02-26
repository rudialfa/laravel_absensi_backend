<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Leaves;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeLeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $q = Leaves::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['approver:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('from')) $q->whereDate('start_date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('end_date', '<=', $request->to);

        return response()->json([
            'status' => true,
            'message' => 'List leaves',
            'data' => $q->paginate((int)($request->get('per_page', 10))),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $data = Leaves::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->with(['approver:id,name'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Leave detail',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['nullable', 'in:annual,sick,maternity,important,other'],
            'reason' => ['nullable', 'string'],
            'attachment' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => $v->errors()->first(),
                'errors' => $v->errors(),
            ], 422);
        }

        $leave = Leaves::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'type' => $request->type ?? 'annual',
            'reason' => $request->reason,
            'attachment' => $request->attachment,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Leave request created',
            'data' => $leave,
        ], 201);
    }

    public function cancel(Request $request, int $id)
    {
        $user = $request->user();

        $leave = Leaves::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'status' => false,
                'message' => 'Hanya cuti pending yang bisa dibatalkan.',
            ], 422);
        }

        $leave->update(['status' => 'canceled']);

        return response()->json([
            'status' => true,
            'message' => 'Leave request canceled',
            'data' => $leave,
        ]);
    }
}
