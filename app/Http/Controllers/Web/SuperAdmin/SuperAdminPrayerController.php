<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Prayer;
use App\Services\PrayerTimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Jadwal Sholat (tabel prayers: per kota per tanggal) yang dipakai aplikasi santri & ustadz.
 *
 *  - Ringkasan: kota-kota tenant & apakah jadwalnya sudah tersedia (bulan ini, bulan depan).
 *  - Kalender 1 kota × 1 bulan: lihat, koreksi manual per hari, tambah hari yang kosong.
 *  - Ambil dari API Aladhan (KEMENAG) untuk 1–12 bulan; hari yang dikoreksi manual tidak ditimpa
 *    kecuali dipilih.
 *  - Hapus 1 hari / 1 bulan, export CSV.
 */
class SuperAdminPrayerController extends Controller
{
    public function __construct(private PrayerTimeService $service) {}

    // =========================================================
    // INDEX (?city=&month=Y-m)
    // =========================================================
    public function index(Request $request)
    {
        $tz = 'Asia/Jakarta';
        $tenantCities = $this->service->tenantCities();

        // Semua kota yang ada di tabel + kota tenant
        $dbCities = Prayer::query()->selectRaw('city, count(*) as total, min(date) as first_date, max(date) as last_date')
            ->groupBy('city')->get()->keyBy('city');

        $thisMonth = now($tz)->startOfMonth();
        $nextMonth = $thisMonth->copy()->addMonthNoOverflow();

        $monthCount = fn(Carbon $m) => Prayer::whereYear('date', $m->year)->whereMonth('date', $m->month)
            ->selectRaw('city, count(*) as n')->groupBy('city')->pluck('n', 'city');
        $countThis = $monthCount($thisMonth);
        $countNext = $monthCount($nextMonth);

        $cities = $tenantCities->keys()->merge($dbCities->keys())->unique()->sort()->values()->map(fn($city) => [
            'city'       => $city,
            'tenants'    => (int) ($tenantCities[$city] ?? 0),
            'total'      => (int) ($dbCities[$city]->total ?? 0),
            'last_date'  => $dbCities[$city]->last_date ?? null,
            'this_month' => (int) ($countThis[$city] ?? 0),
            'next_month' => (int) ($countNext[$city] ?? 0),
        ]);

        $selectedCity = $request->city ?: ($cities->firstWhere('tenants', '>', 0)['city'] ?? $cities->first()['city'] ?? PrayerTimeService::FALLBACK_CITY);

        try {
            $month = $request->month ? Carbon::createFromFormat('Y-m', $request->month, $tz)->startOfMonth() : $thisMonth->copy();
        } catch (\Throwable) {
            $month = $thisMonth->copy();
        }

        $rows = Prayer::where('city', $selectedCity)
            ->whereYear('date', $month->year)->whereMonth('date', $month->month)
            ->get()
            ->keyBy(fn($p) => Carbon::parse($p->date)->toDateString());

        return view('pages.superadmin.prayers.index', [
            'cities'         => $cities,
            'selectedCity'   => $selectedCity,
            'month'          => $month,
            'thisMonth'      => $thisMonth,
            'nextMonth'      => $nextMonth,
            'rows'           => $rows,
            'times'          => PrayerTimeService::TIMES,
            'noCityTenants'  => Company::where('is_active', true)->whereIn('type', ['pesantren', 'school'])
                ->where(fn($q) => $q->whereNull('city')->orWhere('city', ''))->orderBy('name')->get(['id', 'name', 'type']),
            'cityTenants'    => Company::where('is_active', true)->whereIn('type', ['pesantren', 'school'])
                ->where('city', $selectedCity)->orderBy('name')->get(['id', 'name', 'type']),
            'fallbackCity'   => PrayerTimeService::FALLBACK_CITY,
        ]);
    }

    // =========================================================
    // AMBIL DARI API
    // =========================================================
    public function fetch(Request $request)
    {
        $data = $request->validate([
            'city'      => 'required|string|max:100',
            'month'     => 'required|date_format:Y-m',
            'months'    => 'required|integer|min:1|max:12',
            'overwrite' => 'nullable|boolean',
        ], [], ['city' => 'kota', 'month' => 'bulan mulai', 'months' => 'jumlah bulan']);

        @set_time_limit(180);

        $city = trim($data['city']);
        $start = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        $ok = [];
        $errors = [];
        $saved = 0;
        $skipped = 0;

        for ($i = 0; $i < (int) $data['months']; $i++) {
            $m = $start->copy()->addMonthsNoOverflow($i);
            try {
                $r = $this->service->fetchMonth($city, $m->year, $m->month, $request->boolean('overwrite'));
                $saved += $r['saved'];
                $skipped += $r['skipped_manual'];
                $ok[] = $m->translatedFormat('M Y');
            } catch (\Throwable $e) {
                $errors[] = $m->translatedFormat('M Y') . ': ' . $e->getMessage();
            }
        }

        AuditLog::record('fetch_prayer_times', null, "Ambil jadwal sholat {$city} " . $start->format('Y-m') . " ({$data['months']} bulan) dari API", [
            'saved' => $saved,
            'skipped_manual' => $skipped,
            'errors' => $errors,
        ]);

        $redirect = redirect()->route('superadmin.prayers.index', ['city' => $city, 'month' => $start->format('Y-m')]);

        if (!$ok) {
            return $redirect->with('error', 'Gagal mengambil jadwal. ' . implode(' | ', $errors));
        }

        $msg = "{$saved} hari jadwal {$city} tersimpan (" . implode(', ', $ok) . ').';
        if ($skipped) {
            $msg .= " {$skipped} hari koreksi manual tidak ditimpa.";
        }

        $redirect = $redirect->with('success', $msg);

        return $errors ? $redirect->with('warning', 'Sebagian gagal: ' . implode(' | ', $errors)) : $redirect;
    }

    // =========================================================
    // SIMPAN 1 HARI (tambah / koreksi manual)
    // =========================================================
    public function save(Request $request)
    {
        $rules = ['city' => 'required|string|max:100', 'date' => 'required|date'];
        foreach (array_keys(PrayerTimeService::TIMES) as $f) {
            $rules[$f] = 'required|date_format:H:i';
        }
        $data = $request->validate($rules, [], ['fajr' => 'Subuh', 'dzuhur' => 'Dzuhur', 'ashar' => 'Ashar', 'maghrib' => 'Maghrib', 'isya' => 'Isya']);

        // Urutan jam harus naik: Subuh < Dzuhur < Ashar < Maghrib < Isya
        $seq = array_map(fn($f) => $data[$f], array_keys(PrayerTimeService::TIMES));
        $sorted = $seq;
        sort($sorted);
        if ($seq !== $sorted || count(array_unique($seq)) !== count($seq)) {
            return back()->withInput()->with('error', 'Urutan jam tidak masuk akal (Subuh < Dzuhur < Ashar < Maghrib < Isya).');
        }

        $date = Carbon::parse($data['date'])->toDateString();
        $city = trim($data['city']);

        $prayer = Prayer::firstOrNew(['city' => $city, 'date' => $date]);
        $before = $prayer->exists ? collect(PrayerTimeService::TIMES)->keys()->mapWithKeys(fn($f) => [$f => $prayer->hm($f)])->all() : null;

        $prayer->forceFill(array_intersect_key($data, PrayerTimeService::TIMES) + ['source' => 'manual'])->save();

        AuditLog::record($before ? 'update_prayer_time' : 'create_prayer_time', null, "Jadwal sholat {$city} {$date} " . ($before ? 'dikoreksi' : 'ditambahkan') . ' manual', [
            'before' => $before,
            'after'  => array_intersect_key($data, PrayerTimeService::TIMES),
        ]);

        return redirect()->route('superadmin.prayers.index', ['city' => $city, 'month' => substr($date, 0, 7)])
            ->with('success', "Jadwal {$city} " . Carbon::parse($date)->translatedFormat('d M Y') . ' disimpan.');
    }

    // =========================================================
    // HAPUS
    // =========================================================
    public function destroy($id)
    {
        $prayer = Prayer::findOrFail($id);
        $snapshot = $prayer->getAttributes();
        $prayer->delete();

        AuditLog::record('delete_prayer_time', null, "Jadwal sholat {$snapshot['city']} {$snapshot['date']} dihapus", $snapshot);

        return back()->with('success', 'Jadwal 1 hari dihapus. Aplikasi akan mengambil ulang dari API saat dibuka.');
    }

    /** Hapus 1 kota × 1 bulan */
    public function destroyMonth(Request $request)
    {
        $data = $request->validate([
            'city'   => 'required|string|max:100',
            'month'  => 'required|date_format:Y-m',
            'reason' => 'required|string|max:500',
        ], [], ['reason' => 'alasan']);

        $m = Carbon::createFromFormat('Y-m', $data['month']);
        $deleted = Prayer::where('city', $data['city'])->whereYear('date', $m->year)->whereMonth('date', $m->month)->delete();

        AuditLog::record('delete_prayer_month', null, "Jadwal sholat {$data['city']} {$data['month']} dihapus ({$deleted} hari). Alasan: {$data['reason']}", $data + ['deleted' => $deleted]);

        return back()->with('success', "{$deleted} hari jadwal {$data['city']} {$m->translatedFormat('F Y')} dihapus.");
    }

    // =========================================================
    // EXPORT
    // =========================================================
    public function export(Request $request): StreamedResponse
    {
        $city = (string) $request->city;
        $m = $request->month ? Carbon::createFromFormat('Y-m', $request->month) : now();

        return response()->streamDownload(function () use ($city, $m) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kota', 'Tanggal', 'Hari', 'Subuh', 'Dzuhur', 'Ashar', 'Maghrib', 'Isya', 'Sumber'], ';');

            Prayer::where('city', $city)->whereYear('date', $m->year)->whereMonth('date', $m->month)->orderBy('date')
                ->get()->each(function ($p) use ($out) {
                    $d = Carbon::parse($p->date);
                    fputcsv($out, [$p->city, $d->toDateString(), $d->translatedFormat('l'), $p->hm('fajr'), $p->hm('dzuhur'), $p->hm('ashar'), $p->hm('maghrib'), $p->hm('isya'), $p->source], ';');
                });

            fclose($out);
        }, 'jadwal-sholat-' . \Illuminate\Support\Str::slug($city) . '-' . $m->format('Y-m') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
