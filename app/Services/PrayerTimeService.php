<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Prayer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Jadwal sholat dari Aladhan API (metode KEMENAG), disimpan per kota per tanggal di tabel prayers.
 * Dipakai panel superadmin & command `prayers:sync`. Logika sama dengan API santri/ustadz.
 */
class PrayerTimeService
{
    public const API_BASE = 'https://api.aladhan.com/v1';
    public const METHOD = 20; // KEMENAG — Kementerian Agama RI

    /** Kota cadangan yang dipakai aplikasi bila tenant belum mengisi kota */
    public const FALLBACK_CITY = 'Magelang';

    public const TIMES = ['fajr' => 'Subuh', 'dzuhur' => 'Dzuhur', 'ashar' => 'Ashar', 'maghrib' => 'Maghrib', 'isya' => 'Isya'];

    /** "04:12 (WIB)" → "04:12" ; null/kosong → null */
    public static function normalize(?string $time): ?string
    {
        if (!$time) {
            return null;
        }

        $t = substr(trim(preg_replace('/\s*\(.*?\)/', '', $time)), 0, 5);

        return preg_match('/^\d{2}:\d{2}$/', $t) ? $t : null;
    }

    /**
     * Ambil 1 bulan dari API lalu simpan.
     *
     * @return array{saved:int, skipped_manual:int}
     * @throws \RuntimeException kalau API gagal
     */
    public function fetchMonth(string $city, int $year, int $month, bool $overwriteManual = false): array
    {
        $response = Http::timeout(20)->retry(2, 500)->get(self::API_BASE . "/calendarByCity/{$year}/{$month}", [
            'city'    => $city,
            'country' => 'ID',
            'method'  => self::METHOD,
        ]);

        $days = $response->json('data');
        if (!$response->successful() || !is_array($days) || empty($days)) {
            throw new \RuntimeException("API Aladhan tidak mengembalikan jadwal untuk {$city} {$month}/{$year} (HTTP {$response->status()}). Periksa ejaan nama kota.");
        }

        $manualDates = $overwriteManual ? [] : Prayer::where('city', $city)->where('source', 'manual')
            ->whereYear('date', $year)->whereMonth('date', $month)
            ->pluck('date')->map(fn($d) => Carbon::parse($d)->toDateString())->flip()->all();

        $saved = 0;
        $skipped = 0;

        foreach ($days as $day) {
            $date = $this->dateOf($day);
            if (!$date) {
                continue;
            }
            if (isset($manualDates[$date])) {
                $skipped++;
                continue;
            }

            $t = $day['timings'] ?? [];
            Prayer::updateOrCreate(
                ['city' => $city, 'date' => $date],
                [
                    'fajr'    => self::normalize($t['Fajr'] ?? null),
                    'dzuhur'  => self::normalize($t['Dhuhr'] ?? null),
                    'ashar'   => self::normalize($t['Asr'] ?? null),
                    'maghrib' => self::normalize($t['Maghrib'] ?? null),
                    'isya'    => self::normalize($t['Isha'] ?? null),
                    'source'  => 'aladhan_api',
                ]
            );
            $saved++;
        }

        return ['saved' => $saved, 'skipped_manual' => $skipped];
    }

    /** Tanggal Y-m-d dari 1 item calendarByCity */
    private function dateOf(array $day): ?string
    {
        try {
            if (!empty($day['date']['gregorian']['date'])) {
                return Carbon::createFromFormat('d-m-Y', $day['date']['gregorian']['date'])->toDateString();
            }
            if (!empty($day['date']['readable'])) {
                return Carbon::createFromFormat('d M Y', $day['date']['readable'])->toDateString();
            }
            if (!empty($day['date']['timestamp'])) {
                return Carbon::createFromTimestamp($day['date']['timestamp'], 'Asia/Jakarta')->toDateString();
            }
        } catch (\Throwable) {
        }

        return null;
    }

    /**
     * Kota yang dipakai tenant pesantren & sekolah (pemakai jadwal sholat di aplikasi),
     * termasuk kota cadangan untuk tenant yang belum mengisi kota.
     *
     * @return \Illuminate\Support\Collection<string, int> [kota => jumlah tenant]
     */
    public function tenantCities()
    {
        return Company::where('is_active', true)->whereIn('type', ['pesantren', 'school'])->get(['id', 'city'])
            ->groupBy(fn($c) => trim((string) $c->city) !== '' ? trim($c->city) : self::FALLBACK_CITY)
            ->map->count()
            ->sortKeys();
    }
}
