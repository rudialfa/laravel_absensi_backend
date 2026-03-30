<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Prayer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PesantrenPrayerController extends Controller
{
    private const API_BASE = 'https://api.aladhan.com/v1';
    private const METHOD   = 20; // KEMENAG — Kementerian Agama Republik Indonesia

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

    private function city(): string
    {
        return auth()->user()->company?->city ?? 'Magelang';
    }

    private function timezone(): string
    {
        return auth()->user()->company?->timezone ?? 'Asia/Jakarta';
    }

    /**
     * Hapus timezone label dari string waktu API.
     * "06:03 (WIB)" → "06:03"
     */
    private function stripTz(string $time): string
    {
        return substr(trim(preg_replace('/\s*\(.*?\)/', '', $time)), 0, 5);
    }

    /**
     * Format Prayer model → array response.
     */
    private function prayerArray(Prayer $p): array
    {
        return [
            'id'     => $p->id,
            'date'   => (string) $p->date,
            'city'   => $p->city,
            'source' => $p->source,
            'waktu'  => [
                'fajr'    => $p->fajr    ? $this->stripTz($p->fajr)    : null,
                'dzuhur'  => $p->dzuhur  ? $this->stripTz($p->dzuhur)  : null,
                'ashar'   => $p->ashar   ? $this->stripTz($p->ashar)   : null,
                'maghrib' => $p->maghrib ? $this->stripTz($p->maghrib) : null,
                'isya'    => $p->isya    ? $this->stripTz($p->isya)    : null,
            ],
        ];
    }

    /**
     * Cari nama waktu sholat berikutnya dari array waktu.
     * Return null jika semua sudah lewat.
     */
    private function cariBerikutnya(array $waktu, string $now): ?string
    {
        foreach ($waktu as $nama => $jam) {
            if ($jam && $now < $jam) return $nama;
        }
        return null;
    }

    /**
     * Simpan satu hari ke DB dari timings Aladhan.
     */
    private function simpanKeDb(string $date, string $city, array $timings): Prayer
    {
        return Prayer::updateOrCreate(
            ['date' => $date, 'city' => $city],
            [
                'fajr'    => $timings['Fajr']    ?? null,
                'dzuhur'  => $timings['Dhuhr']   ?? null,
                'ashar'   => $timings['Asr']      ?? null,
                'maghrib' => $timings['Maghrib']  ?? null,
                'isya'    => $timings['Isha']     ?? null,
                'source'  => 'aladhan_api',
            ]
        );
    }

    /**
     * Fetch satu hari dari /timingsByCity/{date}.
     * DB format: YYYY-MM-DD → API expects: DD-MM-YYYY
     */
    private function fetchOneDay(string $date, string $city): ?Prayer
    {
        try {
            $apiDate  = Carbon::parse($date)->format('d-m-Y');
            $response = Http::timeout(8)->get(self::API_BASE . "/timingsByCity/{$apiDate}", [
                'city'    => $city,
                'country' => 'ID',
                'method'  => self::METHOD,
            ]);

            if (!$response->successful()) return null;
            $timings = $response->json('data.timings');
            if (!$timings) return null;

            return $this->simpanKeDb($date, $city, $timings);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ambil dari DB dulu, jika tidak ada fetch dari API.
     */
    private function getPrayer(string $date, string $city): ?Prayer
    {
        return Prayer::where('date', $date)->where('city', $city)->first()
            ?? $this->fetchOneDay($date, $city);
    }

    /**
     * Hitung sisa menit dari sekarang ke waktu sholat berikutnya.
     */
    private function hitungSisaMenit(string $date, string $waktu, string $timezone): int
    {
        try {
            $nextTime = Carbon::parse($date . ' ' . $waktu, $timezone);
            return max(0, (int) now($timezone)->diffInMinutes($nextTime, false));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Format sisa menit jadi label readable.
     * 75 → "1 jam 15 menit" | 45 → "45 menit"
     */
    private function formatSisa(int $menit): string
    {
        if ($menit >= 60) {
            return floor($menit / 60) . ' jam ' . ($menit % 60) . ' menit';
        }
        return $menit . ' menit';
    }

    // ============================================================
    // TODAY — GET /api/pesantren/prayers/today
    // Jadwal sholat hari ini + waktu sholat berikutnya
    // ============================================================
    public function today(): JsonResponse
    {
        $this->ensureUstadz();

        $today  = now($this->timezone())->toDateString();
        $city   = $this->city();
        $prayer = $this->getPrayer($today, $city);

        if (!$prayer) {
            return response()->json([
                'status'  => false,
                'message' => 'Jadwal sholat tidak tersedia. Periksa koneksi internet atau coba lagi nanti.',
            ], 503);
        }

        $waktu      = $this->prayerArray($prayer)['waktu'];
        $now        = now($this->timezone())->format('H:i');
        $berikutnya = $this->cariBerikutnya($waktu, $now);

        return response()->json([
            'status'  => true,
            'message' => 'Jadwal sholat hari ini',
            'data'    => array_merge($this->prayerArray($prayer), [
                'berikutnya' => $berikutnya, // null jika isya sudah lewat
                'sekarang'   => $now,
            ]),
        ]);
    }

    // ============================================================
    // BY DATE — GET /api/pesantren/prayers/{date}
    // Jadwal sholat tanggal tertentu (format: YYYY-MM-DD)
    // ============================================================
    public function byDate(string $date): JsonResponse
    {
        $this->ensureUstadz();

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'status'  => false,
                'message' => 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.',
            ], 422);
        }

        $city   = $this->city();
        $prayer = $this->getPrayer($date, $city);

        if (!$prayer) {
            return response()->json([
                'status'  => false,
                'message' => "Jadwal sholat tanggal {$date} tidak tersedia.",
            ], 503);
        }

        return response()->json([
            'status'  => true,
            'message' => "Jadwal sholat tanggal {$date}",
            'data'    => $this->prayerArray($prayer),
        ]);
    }

    // ============================================================
    // NEXT — GET /api/pesantren/prayers/next
    // Waktu sholat berikutnya real-time dari Aladhan API
    //
    // Pakai /nextTimingsByCity — server Aladhan yang hitung
    // berdasarkan waktu sekarang, tidak perlu hitung manual.
    // Ada fallback ke DB jika API tidak tersedia.
    //
    // Response: { nama, waktu, sisa_menit, sisa_label, sekarang }
    // ============================================================
    public function next(): JsonResponse
    {
        $this->ensureUstadz();

        $city    = $this->city();
        $today   = now($this->timezone())->toDateString();
        $apiDate = Carbon::parse($today)->format('d-m-Y');

        try {
            $response = Http::timeout(8)->get(
                self::API_BASE . "/nextTimingsByCity/{$apiDate}",
                ['city' => $city, 'country' => 'ID', 'method' => self::METHOD]
            );

            if (!$response->successful()) throw new \Exception('API tidak merespons');

            $timings = $response->json('data.timings');
            if (empty($timings)) throw new \Exception('Data waktu tidak valid');

            // timings berisi 1 entry: misal {"Dhuhr": "12:04"}
            $namaAsli  = array_key_first($timings);
            $waktuNext = $this->stripTz($timings[$namaAsli]);

            $namaMap = [
                'Fajr'    => 'fajr',
                'Dhuhr'   => 'dzuhur',
                'Asr'     => 'ashar',
                'Maghrib' => 'maghrib',
                'Isha'    => 'isya',
            ];
            $namaLokal = $namaMap[$namaAsli] ?? strtolower($namaAsli);
            $sisaMenit = $this->hitungSisaMenit($today, $waktuNext, $this->timezone());

            return response()->json([
                'status'  => true,
                'message' => 'Waktu sholat berikutnya',
                'data'    => [
                    'nama'       => $namaLokal,
                    'nama_asli'  => $namaAsli,
                    'waktu'      => $waktuNext,
                    'sisa_menit' => $sisaMenit,
                    'sisa_label' => $this->formatSisa($sisaMenit),
                    'sekarang'   => now($this->timezone())->format('H:i'),
                    'city'       => $city,
                    'tanggal'    => $today,
                    'source'     => 'aladhan_api',
                ],
            ]);
        } catch (\Exception $e) {
            // Fallback: hitung manual dari DB
            $prayer = $this->getPrayer($today, $city);
            if (!$prayer) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Waktu sholat berikutnya tidak tersedia.',
                ], 503);
            }

            $waktu      = $this->prayerArray($prayer)['waktu'];
            $now        = now($this->timezone())->format('H:i');
            $berikutnya = $this->cariBerikutnya($waktu, $now);

            if (!$berikutnya) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Semua waktu sholat hari ini sudah lewat',
                    'data'    => [
                        'nama'       => null,
                        'waktu'      => null,
                        'sisa_menit' => 0,
                        'sisa_label' => '-',
                        'sekarang'   => $now,
                        'city'       => $city,
                        'tanggal'    => $today,
                        'source'     => 'database_fallback',
                    ],
                ]);
            }

            $sisaMenit = $this->hitungSisaMenit($today, $waktu[$berikutnya], $this->timezone());

            return response()->json([
                'status'  => true,
                'message' => 'Waktu sholat berikutnya (dari cache)',
                'data'    => [
                    'nama'       => $berikutnya,
                    'waktu'      => $waktu[$berikutnya],
                    'sisa_menit' => $sisaMenit,
                    'sisa_label' => $this->formatSisa($sisaMenit),
                    'sekarang'   => $now,
                    'city'       => $city,
                    'tanggal'    => $today,
                    'source'     => 'database_fallback',
                ],
            ]);
        }
    }

    // ============================================================
    // MONTHLY — GET /api/pesantren/prayers/monthly?month=3&year=2026
    // Jadwal sholat satu bulan penuh
    //
    // Pakai GET /calendarByCity/{year}/{month} — satu API call
    // dapat 30 hari sekaligus, jauh lebih efisien dari loop per hari.
    // Jika DB sudah lengkap langsung return dari DB (tidak hit API).
    // ============================================================
    public function monthly(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $month = (int) $request->get('month', now($this->timezone())->month);
        $year  = (int) $request->get('year',  now($this->timezone())->year);
        $city  = $this->city();

        if ($month < 1 || $month > 12) {
            return response()->json([
                'status'  => false,
                'message' => 'Bulan tidak valid (1-12).',
            ], 422);
        }

        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Cek DB dulu
        $existing = Prayer::where('city', $city)
            ->whereMonth('date', $month)
            ->whereYear('date',  $year)
            ->orderBy('date')
            ->get()
            ->keyBy(fn($p) => $p->date->format('Y-m-d'));

        // DB sudah lengkap — langsung return
        if ($existing->count() >= $daysInMonth) {
            return response()->json([
                'status'  => true,
                'message' => "Jadwal sholat bulan {$month}/{$year}",
                'data'    => [
                    'month'   => $month,
                    'year'    => $year,
                    'city'    => $city,
                    'total'   => $existing->count(),
                    'source'  => 'database',
                    'prayers' => $existing->values()->map(fn($p) => $this->prayerArray($p)),
                ],
            ]);
        }

        // Fetch satu bulan sekaligus — /calendarByCity/{year}/{month}
        try {
            $response = Http::timeout(15)->get(
                self::API_BASE . "/calendarByCity/{$year}/{$month}",
                ['city' => $city, 'country' => 'ID', 'method' => self::METHOD]
            );

            if (!$response->successful()) throw new \Exception('API gagal');

            $days = $response->json('data');
            if (!is_array($days) || empty($days)) throw new \Exception('Data kosong');

            $result = [];
            foreach ($days as $day) {
                $timings  = $day['timings'] ?? [];
                $readable = $day['date']['readable'] ?? null;
                $dateStr  = $readable
                    ? Carbon::createFromFormat('d M Y', $readable)->format('Y-m-d')
                    : Carbon::createFromTimestamp($day['date']['timestamp'] ?? 0)->format('Y-m-d');

                $prayer   = $this->simpanKeDb($dateStr, $city, $timings);
                $result[] = $this->prayerArray($prayer);
            }

            return response()->json([
                'status'  => true,
                'message' => "Jadwal sholat bulan {$month}/{$year}",
                'data'    => [
                    'month'   => $month,
                    'year'    => $year,
                    'city'    => $city,
                    'total'   => count($result),
                    'source'  => 'aladhan_api',
                    'prayers' => $result,
                ],
            ]);
        } catch (\Exception $e) {
            // Fallback: return yang ada di DB saja
            if ($existing->isNotEmpty()) {
                return response()->json([
                    'status'  => true,
                    'message' => "Jadwal sholat bulan {$month}/{$year} (sebagian dari cache)",
                    'data'    => [
                        'month'   => $month,
                        'year'    => $year,
                        'city'    => $city,
                        'total'   => $existing->count(),
                        'source'  => 'database_partial',
                        'prayers' => $existing->values()->map(fn($p) => $this->prayerArray($p)),
                    ],
                ]);
            }

            return response()->json([
                'status'  => false,
                'message' => 'Jadwal sholat bulanan tidak tersedia. Periksa koneksi internet.',
            ], 503);
        }
    }

    // ============================================================
    // METHODS — GET /api/pesantren/prayers/methods
    // Daftar semua metode kalkulasi sholat Aladhan
    //
    // Untuk dropdown di PesantrenSettingController —
    // ustadz bisa pilih metode yang sesuai dengan daerahnya.
    // ============================================================
    public function methods(): JsonResponse
    {
        $this->ensureUstadz();

        try {
            $response = Http::timeout(8)->get(self::API_BASE . '/methods');
            if (!$response->successful()) throw new \Exception('API gagal');

            $data = $response->json('data');

            $methods = collect($data)
                ->filter(fn($m) => isset($m['id']))
                ->map(fn($m) => [
                    'id'       => $m['id'],
                    'name'     => $m['name'],
                    'params'   => $m['params']   ?? [],
                    'location' => $m['location'] ?? null,
                ])
                ->sortBy('id')
                ->values();

            return response()->json([
                'status'       => true,
                'message'      => 'Daftar metode kalkulasi sholat',
                'method_aktif' => self::METHOD,
                'data'         => $methods,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Daftar metode tidak tersedia.',
            ], 503);
        }
    }
}
