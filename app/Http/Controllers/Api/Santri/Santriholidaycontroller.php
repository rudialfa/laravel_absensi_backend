<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class Santriholidaycontroller extends Controller
{
    // private function ensureSantri(): void
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'santri') {
    //         abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus Santri)'], 403));
    //     }
    // }

    // private function transformItem(CompanyHoliday $item): array
    // {
    //     $start = Carbon::parse($item->start_date);
    //     $end   = Carbon::parse($item->end_date);
    //     $today = now()->toDateString();

    //     return [
    //         'id'              => (int) $item->id,
    //         'company_id'      => (int) $item->company_id,
    //         'start_date'      => $item->start_date,
    //         'end_date'        => $item->end_date,
    //         'name'            => $item->name,
    //         'type'            => $item->type,
    //         'note'            => $item->note,
    //         'total_days'      => $start->diffInDays($end) + 1,
    //         'is_active_today' => $today >= $item->start_date && $today <= $item->end_date,
    //         'created_at'      => optional($item->created_at)->toDateTimeString(),
    //         'updated_at'      => optional($item->updated_at)->toDateTimeString(),
    //     ];
    // }

    // // GET /api/pesantren/santri/holidays
    // // Sejajar: EmployeeHolidayController::index()
    // public function index(Request $request): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $companyId = auth()->user()->company_id;

    //     $q = CompanyHoliday::query()
    //         ->where('company_id', $companyId)
    //         ->orderByDesc('start_date');

    //     if ($request->filled('type')) $q->where('type', $request->type);

    //     if ($request->filled('q')) {
    //         $search = trim($request->q);
    //         $q->where(function ($sub) use ($search) {
    //             $sub->where('name', 'like', "%{$search}%")
    //                 ->orWhere('note', 'like', "%{$search}%");
    //         });
    //     }

    //     if ($request->filled('from') && $request->filled('to')) {
    //         $q->where(function ($sub) use ($request) {
    //             $sub->whereDate('start_date', '<=', $request->to)
    //                 ->whereDate('end_date',   '>=', $request->from);
    //         });
    //     } else {
    //         if ($request->filled('from')) $q->whereDate('end_date',   '>=', $request->from);
    //         if ($request->filled('to'))   $q->whereDate('start_date', '<=', $request->to);
    //     }

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'List hari libur pesantren',
    //         'data'    => $q->paginate((int) $request->get('per_page', 10))
    //             ->through(fn($item) => $this->transformItem($item)),
    //     ]);
    // }

    // // GET /api/pesantren/santri/holidays/{id}
    // // Sejajar: EmployeeHolidayController::show()
    // public function show(int $id): JsonResponse
    // {
    //     $this->ensureSantri();

    //     $data = CompanyHoliday::query()
    //         ->where('company_id', auth()->user()->company_id)
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status'  => true,
    //         'message' => 'Detail hari libur',
    //         'data'    => $this->transformItem($data),
    //     ]);
    // }

    // kode 2
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Santri)',
            ], 403));
        }
    }

    private function transformItem(CompanyHoliday $item): array
    {
        $start = Carbon::parse($item->start_date);
        $end   = Carbon::parse($item->end_date);
        $today = now()->toDateString();

        return [
            'id'              => (int) $item->id,
            'company_id'      => (int) $item->company_id,
            'start_date'      => $item->start_date,
            'end_date'        => $item->end_date,
            'name'            => $item->name,
            'type'            => $item->type,
            'note'            => $item->note,
            'total_days'      => $start->diffInDays($end) + 1,
            'is_active_today' => $today >= $item->start_date && $today <= $item->end_date,
            'created_at'      => optional($item->created_at)->toDateTimeString(),
            'updated_at'      => optional($item->updated_at)->toDateTimeString(),
        ];
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri/holidays
    // Sejajar: EmployeeHolidayController::index()
    // Query: type, q, from, to, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $companyId = auth()->user()->company_id;

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

        if ($request->filled('from') && $request->filled('to')) {
            $q->where(function ($sub) use ($request) {
                $sub->whereDate('start_date', '<=', $request->to)
                    ->whereDate('end_date',   '>=', $request->from);
            });
        } else {
            if ($request->filled('from')) $q->whereDate('end_date',   '>=', $request->from);
            if ($request->filled('to'))   $q->whereDate('start_date', '<=', $request->to);
        }

        return response()->json([
            'status'  => true,
            'message' => 'List hari libur pesantren',
            'data'    => $q->paginate((int) $request->get('per_page', 10))
                ->through(fn($item) => $this->transformItem($item)),
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/holidays/{id}
    // Sejajar: EmployeeHolidayController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $data = CompanyHoliday::query()
            ->where('company_id', auth()->user()->company_id)
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail hari libur',
            'data'    => $this->transformItem($data),
        ]);
    }
}
