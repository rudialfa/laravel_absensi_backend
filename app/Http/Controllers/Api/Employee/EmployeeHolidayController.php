<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EmployeeHolidayController extends Controller
{
    private function ensureEmployee()
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus Employee)',
            ], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureEmployee();

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

        // overlap filter range
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
            'data' => $q->paginate((int) ($request->get('per_page', 10)))
                ->through(fn($item) => $this->transformItem($item)),
        ], 200);
    }

    public function show($id)
    {
        $this->ensureEmployee();

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
