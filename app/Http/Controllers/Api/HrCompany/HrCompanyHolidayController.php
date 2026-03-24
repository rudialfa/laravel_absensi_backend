<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;


class HrCompanyHolidayController extends Controller
{

    // private function ensureHr()
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'hr') {
    //         abort(response()->json([
    //             'status' => false,
    //             'message' => 'Akses ditolak (khusus HR)',
    //         ], 403));
    //     }
    // }

    // private function companyId()
    // {
    //     return auth()->user()->company_id ?? null;
    // }

    // public function index(Request $request)
    // {
    //     $this->ensureHr();

    //     $companyId = $this->companyId();

    //     $q = CompanyHoliday::query()
    //         ->where('company_id', $companyId)
    //         ->orderByDesc('start_date')
    //         ->orderByDesc('end_date');

    //     if ($request->filled('type')) {
    //         $q->where('type', $request->type);
    //     }

    //     if ($request->filled('q')) {
    //         $search = trim($request->q);
    //         $q->where(function ($sub) use ($search) {
    //             $sub->where('name', 'like', "%{$search}%")
    //                 ->orWhere('note', 'like', "%{$search}%");
    //         });
    //     }

    //     // range overlap filter
    //     if ($request->filled('from') && $request->filled('to')) {
    //         $from = $request->from;
    //         $to = $request->to;

    //         $q->where(function ($sub) use ($from, $to) {
    //             $sub->whereDate('start_date', '<=', $to)
    //                 ->whereDate('end_date', '>=', $from);
    //         });
    //     } else {
    //         if ($request->filled('from')) {
    //             $q->whereDate('end_date', '>=', $request->from);
    //         }

    //         if ($request->filled('to')) {
    //             $q->whereDate('start_date', '<=', $request->to);
    //         }
    //     }

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List holidays',
    //         'data' => $q->paginate((int) ($request->get('per_page', 10))),
    //     ], 200);
    // }

    // public function store(Request $request)
    // {
    //     $this->ensureHr();

    //     $companyId = $this->companyId();

    //     $validated = $request->validate([
    //         'start_date' => ['required', 'date'],
    //         'end_date' => ['required', 'date', 'after_or_equal:start_date'],
    //         'name' => ['required', 'string', 'max:255'],
    //         'type' => ['required', 'in:company,national'],
    //         'note' => ['nullable', 'string'],
    //     ]);

    //     $data = CompanyHoliday::create([
    //         'company_id' => $companyId,
    //         'start_date' => $validated['start_date'],
    //         'end_date' => $validated['end_date'],
    //         'name' => $validated['name'],
    //         'type' => $validated['type'],
    //         'note' => $validated['note'] ?? null,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday berhasil ditambahkan',
    //         'data' => $this->transformItem($data),
    //     ], 201);
    // }

    // public function show($id)
    // {
    //     $this->ensureHr();

    //     $companyId = $this->companyId();

    //     $data = CompanyHoliday::query()
    //         ->where('company_id', $companyId)
    //         ->findOrFail($id);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Detail holiday',
    //         'data' => $this->transformItem($data),
    //     ], 200);
    // }

    // public function update(Request $request, $id)
    // {
    //     $this->ensureHr();

    //     $companyId = $this->companyId();

    //     $validated = $request->validate([
    //         'start_date' => ['required', 'date'],
    //         'end_date' => ['required', 'date', 'after_or_equal:start_date'],
    //         'name' => ['required', 'string', 'max:255'],
    //         'type' => ['required', 'in:company,national'],
    //         'note' => ['nullable', 'string'],
    //     ]);

    //     $data = CompanyHoliday::query()
    //         ->where('company_id', $companyId)
    //         ->findOrFail($id);

    //     $data->update([
    //         'start_date' => $validated['start_date'],
    //         'end_date' => $validated['end_date'],
    //         'name' => $validated['name'],
    //         'type' => $validated['type'],
    //         'note' => $validated['note'] ?? null,
    //     ]);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday berhasil diperbarui',
    //         'data' => $this->transformItem($data->fresh()),
    //     ], 200);
    // }

    // public function destroy($id)
    // {
    //     $this->ensureHr();

    //     $companyId = $this->companyId();

    //     $data = CompanyHoliday::query()
    //         ->where('company_id', $companyId)
    //         ->findOrFail($id);

    //     $data->delete();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Holiday berhasil dihapus',
    //     ], 200);
    // }

    // private function transformItem(CompanyHoliday $item): array
    // {
    //     $start = Carbon::parse($item->start_date);
    //     $end = Carbon::parse($item->end_date);
    //     $today = now()->toDateString();

    //     return [
    //         'id' => (int) $item->id,
    //         'company_id' => (int) $item->company_id,
    //         'start_date' => $item->start_date,
    //         'end_date' => $item->end_date,
    //         'name' => $item->name,
    //         'type' => $item->type,
    //         'note' => $item->note,
    //         'total_days' => $start->diffInDays($end) + 1,
    //         'is_active_today' => $today >= $item->start_date && $today <= $item->end_date,
    //         'created_at' => optional($item->created_at)->toDateTimeString(),
    //         'updated_at' => optional($item->updated_at)->toDateTimeString(),
    //     ];
    // }

    // kode 2
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus HR)',
            ], 403));
        }
    }

    private function companyId(): ?int
    {
        return auth()->user()->company_id ?? null;
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
    // GET /api/company/hr/holidays
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

        $q = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
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
            $from = $request->from;
            $to   = $request->to;
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
            'status'  => true,
            'message' => 'List holidays',
            'data'    => $q->paginate((int) ($request->get('per_page', 10))),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/holidays
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureHr();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:company,national'],
            'note'       => ['nullable', 'string'],
        ]);

        $data = CompanyHoliday::create([
            'company_id' => $this->companyId(),
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'name'       => $validated['name'],
            'type'       => $validated['type'],
            'note'       => $validated['note'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Holiday berhasil ditambahkan',
            'data'    => $this->transformItem($data),
        ], 201);
    }

    // ============================================================
    // GET /api/company/hr/holidays/{id}
    // ============================================================
    public function show($id)
    {
        $this->ensureHr();

        $data = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail holiday',
            'data'    => $this->transformItem($data),
        ]);
    }

    // ============================================================
    // PUT /api/company/hr/holidays/{id}
    // ============================================================
    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:company,national'],
            'note'       => ['nullable', 'string'],
        ]);

        $data = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $data->update([
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'name'       => $validated['name'],
            'type'       => $validated['type'],
            'note'       => $validated['note'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Holiday berhasil diperbarui',
            'data'    => $this->transformItem($data->fresh()),
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/holidays/{id}
    // ============================================================
    public function destroy($id)
    {
        $this->ensureHr();

        $data = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);

        $data->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Holiday berhasil dihapus',
        ]);
    }

    // ============================================================
    // EXPORT PDF — rekap semua holiday
    // GET /api/company/hr/holidays/export
    //
    // Query params:
    //   type (optional) — company|national
    //   year (optional) — filter tahun dari start_date
    //
    // Install: composer require barryvdh/laravel-dompdf
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $companyId = $this->companyId();
        $user      = auth()->user();

        $q = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->orderBy('start_date');

        if ($request->filled('type')) $q->where('type', $request->type);
        if ($request->filled('year')) $q->whereYear('start_date', $request->year);

        $holidays = $q->get()->map(fn($item) => [
            'id'              => $item->id,
            'name'            => $item->name,
            'type'            => $item->type,
            'start_date'      => $item->start_date,
            'end_date'        => $item->end_date,
            'total_days'      => Carbon::parse($item->start_date)->diffInDays(Carbon::parse($item->end_date)) + 1,
            'note'            => $item->note,
            'is_active_today' => now()->toDateString() >= $item->start_date && now()->toDateString() <= $item->end_date,
        ]);

        $totalCompany  = $holidays->where('type', 'company')->count();
        $totalNational = $holidays->where('type', 'national')->count();
        $totalDays     = $holidays->sum('total_days');

        $year        = $request->filled('year') ? $request->year : now()->year;
        $typeLabel   = $request->filled('type') ? ' - ' . ucfirst($request->type) : '';
        $periodLabel = $year . $typeLabel;
        $fileName    = 'holidays-' . $year . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_holiday', [
            'company'       => $user->company ?? (object)['name' => ''],
            'periodLabel'   => $periodLabel,
            'holidays'      => $holidays,
            'totalCompany'  => $totalCompany,
            'totalNational' => $totalNational,
            'totalDays'     => $totalDays,
            'generatedAt'   => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
