<?php

namespace App\Http\Controllers\Web\SuperAdmin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Services\HolidayNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Hari libur perusahaan (tabel company_holidays, 1 baris = 1 perusahaan).
 * Superadmin boleh CRUD penuh — termasuk menambahkan LIBUR NASIONAL ke banyak perusahaan sekaligus.
 * Aturan sama dengan HrCompanyHolidayController: tanggal tidak boleh bentrok, notifikasi lewat HolidayNotifier.
 */
class SuperAdminHolidayController extends Controller
{
    public function __construct(private HolidayNotifier $notifier) {}

    // =========================================================
    // INDEX
    // =========================================================
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $query = CompanyHoliday::query()
            ->where(fn($q) => $q->whereYear('start_date', $year)->orWhereYear('end_date', $year))
            ->when($request->company_id, fn($q, $id) => $q->where('company_id', $id))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', '%' . addcslashes($s, '%_\\') . '%'));

        match ($request->input('when')) {
            'upcoming' => $query->whereDate('start_date', '>', today()),
            'ongoing'  => $query->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today()),
            'past'     => $query->whereDate('end_date', '<', today()),
            default    => null,
        };

        $holidays = (clone $query)->orderBy('start_date')->orderBy('company_id')->paginate(30)->withQueryString();

        $summary = (clone $query)
            ->selectRaw("sum(type = 'national') as national, sum(type = 'company') as company, count(distinct company_id) as companies")
            ->first();

        // Perusahaan yang belum punya libur nasional sama sekali di tahun ini
        $companies = Company::where('type', 'company')->orderBy('name')->get(['id', 'name', 'is_active']);
        $withNational = CompanyHoliday::where('type', 'national')->whereYear('start_date', $year)->distinct()->pluck('company_id');

        return view('pages.superadmin.monitoring.holidays.index', [
            'holidays'      => $holidays,
            'summary'       => $summary,
            'year'          => $year,
            'years'         => range(now()->year + 1, now()->year - 3),
            'companies'     => $companies,
            'companyNames'  => $companies->pluck('name', 'id'),
            'noNational'    => $companies->whereNotIn('id', $withNational)->values(),
            'typeLabels'    => CompanyHoliday::TYPE_LABELS,
        ]);
    }

    // =========================================================
    // CREATE + STORE (1 atau banyak perusahaan)
    // =========================================================
    public function create(Request $request)
    {
        return view('pages.superadmin.monitoring.holidays.create', [
            'companies'  => Company::where('type', 'company')->orderBy('name')->get(['id', 'name', 'is_active']),
            'typeLabels' => CompanyHoliday::TYPE_LABELS,
            'presetIds'  => array_filter((array) $request->input('company_ids', $request->filled('company_id') ? [$request->company_id] : [])),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(array_merge($this->rules(), [
            'company_ids'   => 'required|array|min:1',
            'company_ids.*' => ['integer', Rule::exists('companies', 'id')->where('type', 'company')],
        ]), $this->messages(), ['company_ids' => 'perusahaan']);

        $created = [];
        $skipped = [];

        DB::transaction(function () use ($data, &$created, &$skipped) {
            foreach (array_unique($data['company_ids']) as $companyId) {
                if ($clash = $this->overlapping((int) $companyId, $data['start_date'], $data['end_date'])) {
                    $skipped[$companyId] = $clash;
                    continue;
                }

                $created[] = CompanyHoliday::create([
                    'company_id' => $companyId,
                    'start_date' => $data['start_date'],
                    'end_date'   => $data['end_date'],
                    'name'       => $data['name'],
                    'type'       => $data['type'],
                    'note'       => $data['note'] ?? null,
                ]);
            }
        });

        if ($request->boolean('notify', true)) {
            $this->notifyLater(collect($created)->filter(fn($h) => $h->time_status !== 'past'), 'created');
        }

        AuditLog::record('create_holiday', null, "Libur \"{$data['name']}\" ditambahkan ke " . count($created) . ' perusahaan', [
            'data'        => collect($data)->except('company_ids')->all(),
            'company_ids' => collect($created)->pluck('company_id')->all(),
            'skipped'     => array_keys($skipped),
        ]);

        $names = Company::whereIn('id', array_keys($skipped))->pluck('name', 'id');
        $redirect = redirect()
            ->route('superadmin.monitoring.holidays.index', ['year' => Carbon::parse($data['start_date'])->year])
            ->with('success', 'Libur "' . $data['name'] . '" ditambahkan ke ' . count($created) . ' perusahaan.'
                . ($request->boolean('notify', true) && count($created) ? ' Notifikasi dikirim ke karyawan.' : ''));

        if ($skipped) {
            $redirect->with('warning', 'Dilewati karena tanggal bentrok: ' . collect($skipped)
                ->map(fn($clash, $id) => ($names[$id] ?? "#{$id}") . " (sudah ada \"{$clash->name}\")")
                ->implode(', ') . '.');
        }

        return $redirect;
    }

    // =========================================================
    // EDIT + UPDATE
    // =========================================================
    public function edit($id)
    {
        $holiday = CompanyHoliday::findOrFail($id);

        // Libur yang sama (nama & tanggal sama) di perusahaan lain
        $siblings = CompanyHoliday::where('id', '!=', $holiday->id)
            ->where('name', $holiday->name)
            ->whereDate('start_date', $holiday->start_date)
            ->whereDate('end_date', $holiday->end_date)
            ->get(['id', 'company_id']);

        return view('pages.superadmin.monitoring.holidays.edit', [
            'holiday'    => $holiday,
            'company'    => Company::find($holiday->company_id),
            'siblings'   => $siblings,
            'companyNames' => Company::pluck('name', 'id'),
            'typeLabels' => CompanyHoliday::TYPE_LABELS,
        ]);
    }

    public function update(Request $request, $id)
    {
        $holiday = CompanyHoliday::findOrFail($id);
        $data    = $request->validate(array_merge($this->rules(), [
            'apply_siblings' => 'nullable|boolean',
        ]), $this->messages());

        // Ikut ubah libur yang sama di perusahaan lain (mis. revisi libur nasional)
        $targets = collect([$holiday]);
        if ($request->boolean('apply_siblings')) {
            $targets = $targets->merge(CompanyHoliday::where('id', '!=', $holiday->id)
                ->where('name', $holiday->name)
                ->whereDate('start_date', $holiday->start_date)
                ->whereDate('end_date', $holiday->end_date)
                ->get());
        }

        $updated = collect();
        $skipped = [];

        DB::transaction(function () use ($targets, $data, &$updated, &$skipped) {
            foreach ($targets as $h) {
                if ($this->overlapping((int) $h->company_id, $data['start_date'], $data['end_date'], $h->id)) {
                    $skipped[] = $h->company_id;
                    continue;
                }

                $h->update([
                    'start_date'       => $data['start_date'],
                    'end_date'         => $data['end_date'],
                    'name'             => $data['name'],
                    'type'             => $data['type'],
                    'note'             => $data['note'] ?? null,
                    // tanggal berubah → pengingat H-1 boleh dikirim ulang
                    'reminder_sent_at' => $h->start_date?->format('Y-m-d') !== $data['start_date'] ? null : $h->reminder_sent_at,
                ]);
                $updated->push($h->fresh());
            }
        });

        if ($request->boolean('notify', true)) {
            $this->notifyLater($updated->filter(fn($h) => $h->time_status !== 'past'), 'updated');
        }

        AuditLog::record('update_holiday', $holiday, "Libur \"{$data['name']}\" diperbarui di {$updated->count()} perusahaan", [
            'data'    => $data,
            'ids'     => $updated->pluck('id')->all(),
            'skipped' => $skipped,
        ]);

        $redirect = redirect()
            ->route('superadmin.monitoring.holidays.index', ['year' => Carbon::parse($data['start_date'])->year])
            ->with('success', "Libur diperbarui ({$updated->count()} perusahaan).");

        if ($skipped) {
            $names = Company::whereIn('id', $skipped)->pluck('name')->implode(', ');
            $redirect->with('warning', "Tidak diubah karena bentrok dengan libur lain: {$names}.");
        }

        return $redirect;
    }

    // =========================================================
    // DESTROY
    // =========================================================
    public function destroy(Request $request, $id)
    {
        $holiday = CompanyHoliday::findOrFail($id);

        $targets = collect([$holiday]);
        if ($request->boolean('apply_siblings')) {
            $targets = $targets->merge(CompanyHoliday::where('id', '!=', $holiday->id)
                ->where('name', $holiday->name)
                ->whereDate('start_date', $holiday->start_date)
                ->whereDate('end_date', $holiday->end_date)
                ->get());
        }

        // Kirim notifikasi "dibatalkan" pakai salinan data sebelum dihapus
        $toNotify = $request->boolean('notify', true)
            ? $targets->filter(fn($h) => $h->time_status !== 'past')->map(fn($h) => $h->replicate()->forceFill(['id' => $h->id]))
            : collect();

        $snapshot = $targets->map->getAttributes()->all();
        CompanyHoliday::whereIn('id', $targets->pluck('id'))->delete();

        $this->notifyLater($toNotify, 'canceled');

        AuditLog::record('delete_holiday', null, "Libur \"{$holiday->name}\" dihapus dari {$targets->count()} perusahaan", ['items' => $snapshot]);

        return redirect()
            ->route('superadmin.monitoring.holidays.index', ['year' => $holiday->start_date?->year])
            ->with('success', "Libur \"{$holiday->name}\" dihapus dari {$targets->count()} perusahaan.");
    }

    // =========================================================
    // HELPER
    // =========================================================
    private function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'name'       => ['required', 'string', 'max:255'],
            'type'       => ['required', 'in:company,national'],
            'note'       => ['nullable', 'string', 'max:1000'],
            'notify'     => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'company_ids.required'    => 'Pilih minimal 1 perusahaan.',
        ];
    }

    private function overlapping(int $companyId, string $start, string $end, ?int $exceptId = null): ?CompanyHoliday
    {
        return CompanyHoliday::where('company_id', $companyId)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->first();
    }

    /** Kirim notifikasi setelah response terkirim (sama seperti HR), error tidak menggagalkan proses */
    private function notifyLater($holidays, string $event): void
    {
        if ($holidays->isEmpty()) {
            return;
        }

        app()->terminating(function () use ($holidays, $event) {
            foreach ($holidays as $h) {
                try {
                    $this->notifier->{$event}($h);
                } catch (\Throwable $e) {
                    Log::error('Gagal kirim notifikasi libur (superadmin)', ['holiday_id' => $h->id, 'error' => $e->getMessage()]);
                }
            }
        });
    }
}
