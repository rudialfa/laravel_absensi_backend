<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PesantrenHolidayController extends Controller
{
       // ============================================================
    // PRIVATE HELPERS
    // ============================================================
 
    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
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
    // INDEX — GET /api/pesantren/holidays
    // Sejajar: HrCompanyHolidayController::index()
    // Query: type, q, from, to, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureUstadz();
 
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
            'data'    => $q->paginate((int) $request->get('per_page', 10)),
        ]);
    }
 
    // ============================================================
    // STORE — POST /api/pesantren/holidays
    // Sejajar: HrCompanyHolidayController::store()
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureUstadz();
 
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
            'message' => 'Hari libur berhasil ditambahkan',
            'data'    => $this->transformItem($data),
        ], 201);
    }
 
    // ============================================================
    // SHOW — GET /api/pesantren/holidays/{id}
    // Sejajar: HrCompanyHolidayController::show()
    // ============================================================
    public function show(int $id)
    {
        $this->ensureUstadz();
 
        $data = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
 
        return response()->json([
            'status'  => true,
            'message' => 'Detail hari libur',
            'data'    => $this->transformItem($data),
        ]);
    }
 
    // ============================================================
    // UPDATE — PUT /api/pesantren/holidays/{id}
    // Sejajar: HrCompanyHolidayController::update()
    // ============================================================
    public function update(Request $request, int $id)
    {
        $this->ensureUstadz();
 
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
            'message' => 'Hari libur berhasil diperbarui',
            'data'    => $this->transformItem($data->fresh()),
        ]);
    }
 
    // ============================================================
    // DESTROY — DELETE /api/pesantren/holidays/{id}
    // Sejajar: HrCompanyHolidayController::destroy()
    // ============================================================
    public function destroy(int $id)
    {
        $this->ensureUstadz();
 
        $data = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
 
        $data->delete();
 
        return response()->json([
            'status'  => true,
            'message' => 'Hari libur berhasil dihapus',
        ]);
    }
 
    // ============================================================
    // EXPORT — GET /api/pesantren/holidays/export
    // Sejajar: HrCompanyHolidayController::export()
    // Query: type (opsional), year (opsional)
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();
 
        $q = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
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
            'is_active_today' => now()->toDateString() >= $item->start_date
                && now()->toDateString() <= $item->end_date,
        ]);
 
        $totalCompany  = $holidays->where('type', 'company')->count();
        $totalNational = $holidays->where('type', 'national')->count();
        $totalDays     = $holidays->sum('total_days');
 
        $year        = $request->filled('year') ? $request->year : now()->year;
        $typeLabel   = $request->filled('type') ? ' - ' . ucfirst($request->type) : '';
        $periodLabel = $year . $typeLabel;
        $fileName    = 'libur-pesantren-' . $year . '.pdf';
 
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_holiday', [
            'company'       => auth()->user()->company ?? (object)['name' => ''],
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
