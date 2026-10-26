<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;

class EmployeeHolidayController extends Controller
{
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Employee)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    // ============================================================
    // GET /api/company/employee/holidays
    // filter: type, q, from, to, upcoming=1, year, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $q = CompanyHoliday::query()->where('company_id', $this->companyId());

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

        if ($request->filled('from') && $request->filled('to')) {
            $q->whereDate('start_date', '<=', $request->to)
                ->whereDate('end_date', '>=', $request->from);
        } else {
            if ($request->filled('from')) $q->whereDate('end_date', '>=', $request->from);
            if ($request->filled('to'))   $q->whereDate('start_date', '<=', $request->to);
        }

        if ($request->filled('year')) {
            $q->whereYear('start_date', $request->year);
        }

        // upcoming=1 → libur terdekat dulu (untuk widget "Libur berikutnya")
        if ($request->boolean('upcoming')) {
            $q->upcoming()->orderBy('start_date');
        } else {
            $q->orderByDesc('start_date')->orderByDesc('end_date');
        }

        $page = $q->paginate((int) $request->get('per_page', 10));
        $page->getCollection()->transform(fn($h) => $h->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'List holidays',
            'data'    => $page,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/holidays/{id}
    // ============================================================
    public function show($id)
    {
        $this->ensureEmployee();

        $holiday = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail holiday',
            'data'    => $holiday->toApiArray(),
        ]);
    }
}