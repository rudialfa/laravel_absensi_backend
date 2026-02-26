<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrCompanyHolidayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $q = CompanyHoliday::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('date');

        if ($request->filled('from')) $q->whereDate('date', '>=', $request->from);
        if ($request->filled('to')) $q->whereDate('date', '<=', $request->to);
        if ($request->filled('type')) $q->where('type', $request->type);

        return response()->json([
            'status' => true,
            'message' => 'List holidays',
            'data' => $q->paginate((int)($request->get('per_page', 10))),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $data = CompanyHoliday::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Holiday detail',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:national,company'],
            'note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => $v->errors()->first(),
                'errors' => $v->errors(),
            ], 422);
        }

        $holiday = CompanyHoliday::create([
            'company_id' => $user->company_id,
            'date' => $request->date,
            'name' => $request->name,
            'type' => $request->type ?? 'company',
            'note' => $request->note,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Holiday created',
            'data' => $holiday,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $user = $request->user();

        $holiday = CompanyHoliday::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        $v = Validator::make($request->all(), [
            'date' => ['sometimes', 'date'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:national,company'],
            'note' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return response()->json([
                'status' => false,
                'message' => $v->errors()->first(),
                'errors' => $v->errors(),
            ], 422);
        }

        $holiday->update($request->only(['date', 'name', 'type', 'note']));

        return response()->json([
            'status' => true,
            'message' => 'Holiday updated',
            'data' => $holiday,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        $holiday = CompanyHoliday::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($id);

        $holiday->delete();

        return response()->json([
            'status' => true,
            'message' => 'Holiday deleted',
        ]);
    }
}
