<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\CompanyHoliday;
use App\Services\HolidayNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HrCompanyHolidayController extends Controller
{
    public function __construct(private HolidayNotifier $notifier) {}

    // ----------------------------------------------------------
    // HELPERS
    // ----------------------------------------------------------

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus HR)',
            ], 403));
        }
    }

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function findOrFail($id): CompanyHoliday
    {
        return CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    /**
     * Kirim notifikasi SETELAH response dikirim ke HR,
     * supaya HR tidak menunggu proses kirim ke semua karyawan.
     * Error notifikasi tidak pernah menggagalkan simpan libur.
     */
    private function notifyLater(callable $fn): void
    {
        app()->terminating(function () use ($fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                Log::error('Gagal kirim notifikasi libur', ['error' => $e->getMessage()]);
            }
        });
    }

    private function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:company,national'],
            'note'       => ['nullable', 'string', 'max:1000'],
            // false = simpan tanpa kirim notifikasi (default: kirim)
            'notify'     => ['nullable', 'boolean'],
        ];
    }

    private function overlapping(string $start, string $end, ?int $exceptId = null): ?CompanyHoliday
    {
        return CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->first();
    }

    // ============================================================
    // GET /api/company/hr/holidays
    // filter: type, q, from, to, upcoming=1, year, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();

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

        if ($request->boolean('upcoming')) {
            $q->upcoming()->orderBy('start_date');
        } else {
            $q->orderByDesc('start_date')->orderByDesc('end_date');
        }

        // Sebelumnya list HR mengirim data mentah (tanpa total_days dll),
        // beda dengan list karyawan. Sekarang formatnya sama.
        $page = $q->paginate((int) $request->get('per_page', 10));
        $page->getCollection()->transform(fn($h) => $h->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'List holidays',
            'data'    => $page,
        ]);
    }

    // ============================================================
    // POST /api/company/hr/holidays
    // body: start_date, end_date, name, type, note, notify (opsional, default true)
    // → otomatis kirim notifikasi ke semua karyawan
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureHr();

        $validated = $request->validate($this->rules(), [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        if ($clash = $this->overlapping($validated['start_date'], $validated['end_date'])) {
            return response()->json([
                'status'  => false,
                'message' => "Tanggal bentrok dengan libur \"{$clash->name}\" ({$clash->period_label}).",
            ], 422);
        }

        $holiday = CompanyHoliday::create([
            'company_id' => $this->companyId(),
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'name'       => $validated['name'],
            'type'       => $validated['type'],
            'note'       => $validated['note'] ?? null,
        ]);

        $willNotify = $request->boolean('notify', true) && $holiday->time_status !== 'past';

        if ($willNotify) {
            $this->notifyLater(fn() => $this->notifier->created($holiday));
        }

        return response()->json([
            'status'       => true,
            'message'      => 'Libur berhasil ditambahkan' . ($willNotify ? ', notifikasi dikirim ke karyawan' : ''),
            'notification' => $willNotify ? 'queued' : 'skipped',
            'data'         => $holiday->toApiArray(),
        ], 201);
    }

    // ============================================================
    // GET /api/company/hr/holidays/{id}
    // ============================================================
    public function show($id)
    {
        $this->ensureHr();

        return response()->json([
            'status'  => true,
            'message' => 'Detail holiday',
            'data'    => $this->findOrFail($id)->toApiArray(),
        ]);
    }

    // ============================================================
    // PUT /api/company/hr/holidays/{id}
    // → kirim notifikasi "diperbarui" kalau tanggal / nama berubah
    // ============================================================
    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $validated = $request->validate($this->rules(), [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);

        $holiday = $this->findOrFail($id);

        if ($clash = $this->overlapping($validated['start_date'], $validated['end_date'], $holiday->id)) {
            return response()->json([
                'status'  => false,
                'message' => "Tanggal bentrok dengan libur \"{$clash->name}\" ({$clash->period_label}).",
            ], 422);
        }

        $oldStart = $holiday->start_date?->format('Y-m-d');
        $oldEnd   = $holiday->end_date?->format('Y-m-d');
        $oldName  = $holiday->name;

        $data = [
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'name'       => $validated['name'],
            'type'       => $validated['type'],
            'note'       => $validated['note'] ?? null,
        ];

        $newStart = Carbon::parse($validated['start_date'])->format('Y-m-d');
        $newEnd   = Carbon::parse($validated['end_date'])->format('Y-m-d');

        // Tanggal mulai berubah → pengingat H-1 harus dikirim ulang
        if ($newStart !== $oldStart) {
            $data['reminder_sent_at'] = null;
        }

        $holiday->update($data);
        $holiday->refresh();

        $changed    = $newStart !== $oldStart || $newEnd !== $oldEnd || $validated['name'] !== $oldName;
        $willNotify = $changed && $request->boolean('notify', true) && $holiday->time_status !== 'past';

        if ($willNotify) {
            $this->notifyLater(fn() => $this->notifier->updated($holiday));
        }

        return response()->json([
            'status'       => true,
            'message'      => 'Libur berhasil diperbarui' . ($willNotify ? ', notifikasi dikirim ke karyawan' : ''),
            'notification' => $willNotify ? 'queued' : 'skipped',
            'data'         => $holiday->toApiArray(),
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/holidays/{id}
    // → kirim notifikasi "dibatalkan" kalau libur belum lewat
    // ============================================================
    public function destroy(Request $request, $id)
    {
        $this->ensureHr();

        $holiday = $this->findOrFail($id);

        $willNotify = $request->boolean('notify', true) && $holiday->time_status !== 'past';

        // Salinan untuk isi notifikasi (model asli sudah terhapus saat notifikasi jalan)
        $snapshot = (new CompanyHoliday())->forceFill($holiday->getAttributes());
        $snapshot->id = $holiday->id;

        $holiday->delete();

        if ($willNotify) {
            $this->notifyLater(fn() => $this->notifier->canceled($snapshot));
        }

        return response()->json([
            'status'       => true,
            'message'      => 'Libur berhasil dihapus' . ($willNotify ? ', notifikasi dikirim ke karyawan' : ''),
            'notification' => $willNotify ? 'queued' : 'skipped',
        ]);
    }

    // ============================================================
    // POST /api/company/hr/holidays/{id}/notify   (BARU)
    // Kirim ulang notifikasi libur secara manual — langsung, bukan antre.
    // Response berisi jumlah terkirim / gagal, berguna untuk tes.
    // ============================================================
    public function notify($id)
    {
        $this->ensureHr();

        $holiday = $this->findOrFail($id);

        if ($holiday->time_status === 'past') {
            return response()->json([
                'status'  => false,
                'message' => 'Libur ini sudah lewat.',
            ], 422);
        }

        try {
            $result = $this->notifier->created($holiday);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Gagal kirim notifikasi: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status'  => true,
            'message' => "Notifikasi terkirim ke {$result['sent']} karyawan",
            'data'    => $result,
        ]);
    }

    // ============================================================
    // EXPORT PDF
    // GET /api/company/hr/holidays/export?type=&year=
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $q = CompanyHoliday::query()
            ->where('company_id', $this->companyId())
            ->orderBy('start_date');

        if ($request->filled('type')) $q->where('type', $request->type);
        if ($request->filled('year')) $q->whereYear('start_date', $request->year);

        $holidays = $q->get()->map(fn($h) => $h->toApiArray());

        $year        = $request->filled('year') ? $request->year : now()->year;
        $typeLabel   = $request->filled('type')
            ? ' - ' . (CompanyHoliday::TYPE_LABELS[$request->type] ?? $request->type)
            : '';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_holiday', [
            'company'       => auth()->user()->company ?? (object) ['name' => ''],
            'periodLabel'   => $year . $typeLabel,
            'holidays'      => $holidays,
            'totalCompany'  => $holidays->where('type', 'company')->count(),
            'totalNational' => $holidays->where('type', 'national')->count(),
            'totalDays'     => $holidays->sum('total_days'),
            'generatedAt'   => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download('holidays-' . $year . '.pdf');
    }
}