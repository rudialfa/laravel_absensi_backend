<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;


class HrCompanyHolidayController extends Controller
{
    // public function index(Request $request)
    // {
    //     $user = $request->user();

    //     $q = CompanyHoliday::query()
    //         ->where('company_id', $user->company_id)
    //         ->orderByDesc('date');

    //     if ($request->filled('from')) $q->whereDate('date', '>=', $request->from);
    //     if ($request->filled('to')) $q->whereDate('date', '<=', $request->to);
    //     if ($request->filled('type')) $q->where('type', $request->type);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List holidays',
    //         'data' => $q->paginate((int)($request->get('per_page', 10))),
    //     ]);
    // }

    // public function show(Request $request, int $id)
    // {
    //     $user = $request->user();

    //     $data = CompanyHoliday::query()
    //         ->where('company_id', $user->company_id)
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday detail',
    //         'data' => $data,
    //     ]);
    // }

    // public function store(Request $request)
    // {
    //     $user = $request->user();

    //     $v = Validator::make($request->all(), [
    //         'date' => ['required', 'date'],
    //         'name' => ['required', 'string', 'max:255'],
    //         'type' => ['nullable', 'in:national,company'],
    //         'note' => ['nullable', 'string'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $v->errors()->first(),
    //             'errors' => $v->errors(),
    //         ], 422);
    //     }

    //     $holiday = CompanyHoliday::create([
    //         'company_id' => $user->company_id,
    //         'date' => $request->date,
    //         'name' => $request->name,
    //         'type' => $request->type ?? 'company',
    //         'note' => $request->note,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday created',
    //         'data' => $holiday,
    //     ], 201);
    // }

    // public function update(Request $request, int $id)
    // {
    //     $user = $request->user();

    //     $holiday = CompanyHoliday::query()
    //         ->where('company_id', $user->company_id)
    //         ->findOrFail($id);

    //     $v = Validator::make($request->all(), [
    //         'date' => ['sometimes', 'date'],
    //         'name' => ['sometimes', 'string', 'max:255'],
    //         'type' => ['sometimes', 'in:national,company'],
    //         'note' => ['nullable', 'string'],
    //     ]);

    //     if ($v->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => $v->errors()->first(),
    //             'errors' => $v->errors(),
    //         ], 422);
    //     }

    //     $holiday->update($request->only(['date', 'name', 'type', 'note']));

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday updated',
    //         'data' => $holiday,
    //     ]);
    // }

    // public function destroy(Request $request, int $id)
    // {
    //     $user = $request->user();

    //     $holiday = CompanyHoliday::query()
    //         ->where('company_id', $user->company_id)
    //         ->findOrFail($id);

    //     $holiday->delete();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday deleted',
    //     ]);
    // }

    // kode 2 revisi
    private function ensureHr()
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus HR)',
            ], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureHr();

        $companyId = $this->companyId();

        $q = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->orderByDesc('end_date');

        if ($request->filled('type')) {
            $q->where('type', $request->type);
        }

        if ($request->filled('q')) {
            $search = trim($request->q);
            $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%");
            });
        }

        // range overlap filter
        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->from;
            $to = $request->to;

            $q->where(function ($sub) use ($from, $to) {
                $sub->whereDate('start_date', '<=', $to)
                    ->whereDate('end_date', '>=', $from);
            });
        } else {
            if ($request->filled('from')) {
                $q->whereDate('end_date', '>=', $request->from);
            }

            if ($request->filled('to')) {
                $q->whereDate('start_date', '<=', $request->to);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'List holidays',
            'data' => $q->paginate((int) ($request->get('per_page', 10))),
        ], 200);
    }

    public function store(Request $request)
    {
        $this->ensureHr();

        $companyId = $this->companyId();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:company,national'],
            'note' => ['nullable', 'string'],
        ]);

        $data = CompanyHoliday::create([
            'company_id' => $companyId,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Holiday berhasil ditambahkan',
            'data' => $this->transformItem($data),
        ], 201);
    }

    public function show($id)
    {
        $this->ensureHr();

        $companyId = $this->companyId();

        $data = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Detail holiday',
            'data' => $this->transformItem($data),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $companyId = $this->companyId();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:company,national'],
            'note' => ['nullable', 'string'],
        ]);

        $data = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $data->update([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Holiday berhasil diperbarui',
            'data' => $this->transformItem($data->fresh()),
        ], 200);
    }

    public function destroy($id)
    {
        $this->ensureHr();

        $companyId = $this->companyId();

        $data = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->findOrFail($id);

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Holiday berhasil dihapus',
        ], 200);
    }

    private function transformItem(CompanyHoliday $item): array
    {
        $start = Carbon::parse($item->start_date);
        $end = Carbon::parse($item->end_date);
        $today = now()->toDateString();

        return [
            'id' => (int) $item->id,
            'company_id' => (int) $item->company_id,
            'start_date' => $item->start_date,
            'end_date' => $item->end_date,
            'name' => $item->name,
            'type' => $item->type,
            'note' => $item->note,
            'total_days' => $start->diffInDays($end) + 1,
            'is_active_today' => $today >= $item->start_date && $today <= $item->end_date,
            'created_at' => optional($item->created_at)->toDateTimeString(),
            'updated_at' => optional($item->updated_at)->toDateTimeString(),
        ];
    }
}
