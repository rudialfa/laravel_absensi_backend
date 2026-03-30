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
 
    private function pesantrenCity(): string
    {
        // Ambil kota dari nama company atau fallback ke Magelang
        // (bisa disesuaikan: simpan kota di tabel companies jika perlu)
        return auth()->user()->company?->city ?? 'Magelang';
    }
 
    private function timezone(): string
    {
        return auth()->user()->company?->timezone ?? 'Asia/Jakarta';
    }
 
    /**
     * Format satu row Prayer menjadi array response.
     */
    private function prayerArray(Prayer $p): array
    {
        return [
            'id'      => $p->id,
            'date'    => (string) $p->date,
            'city'    => $p->city,
            'source'  => $p->source,
            'waktu'   => [
                'fajr'    => $p->fajr    ? substr($p->fajr,    0, 5) : null,
                'dzuhur'  => $p->dzuhur  ? substr($p->dzuhur,  0, 5) : null,
                'ashar'   => $p->ashar   ? substr($p->ashar,   0, 5) : null,
                'maghrib' => $p->maghrib ? substr($p->maghrib, 0, 5) : null,
                'isya'    => $p->isya    ? substr($p->isya,    0, 5) : null,
            ],
        ];
    }
 
    /**
     * Fetch jadwal sholat dari Aladhan API dan simpan ke DB.
     * Return Prayer model jika berhasil, null jika gagal.
     */
    private function fetchFromApi(string $date, string $city): ?Prayer
    {
        try {
            // Aladhan API: https://aladhan.com/prayer-times-api
            $response = Http::timeout(8)->get('https://api.aladhan.com/v1/timingsByCity/' . $date, [
                'city'    => $city,
                'country' => 'ID',
                'method'  => 20, // Kemenag Indonesia
            ]);
 
            if (!$response->successful()) return null;
 
            $timings = $response->json('data.timings');
            if (!$timings) return null;
 
            // Simpan ke DB (upsert: update jika sudah ada, insert jika belum)
            $prayer = Prayer::updateOrCreate(
                ['date' => $date, 'city' => $city],
                [
                    'fajr'    => $timings['Fajr']    ?? null,
                    'dzuhur'  => $timings['Dhuhr']   ?? null,
                    'ashar'   => $timings['Asr']     ?? null,
                    'maghrib' => $timings['Maghrib']  ?? null,
                    'isya'    => $timings['Isha']    ?? null,
                    'source'  => 'aladhan_api',
                ]
            );
 
            return $prayer;
 
        } catch (\Exception $e) {
            return null;
        }
    }
 
    /**
     * Ambil jadwal dari DB, jika tidak ada fetch dari API.
     */
    private function getPrayer(string $date, string $city): ?Prayer
    {
        $prayer = Prayer::where('date', $date)
            ->where('city', $city)
            ->first();
 
        if ($prayer) return $prayer;
 
        // Tidak ada di DB — ambil dari API dan simpan
        return $this->fetchFromApi($date, $city);
    }
 
    // ============================================================
    // TODAY — GET /api/pesantren/prayers/today
    // Jadwal sholat hari ini
    // ============================================================
    public function today(): JsonResponse
    {
        $this->ensureUstadz();
 
        $today = now($this->timezone())->toDateString();
        $city  = $this->pesantrenCity();
 
        $prayer = $this->getPrayer($today, $city);
 
        if (!$prayer) {
            return response()->json([
                'status'  => false,
                'message' => 'Jadwal sholat tidak tersedia. Periksa koneksi internet atau coba lagi nanti.',
            ], 503);
        }
 
        // Tandai waktu sholat berikutnya
        $now     = now($this->timezone())->format('H:i');
        $waktuList = [
            'fajr'    => $prayer->fajr    ? substr($prayer->fajr,    0, 5) : null,
            'dzuhur'  => $prayer->dzuhur  ? substr($prayer->dzuhur,  0, 5) : null,
            'ashar'   => $prayer->ashar   ? substr($prayer->ashar,   0, 5) : null,
            'maghrib' => $prayer->maghrib ? substr($prayer->maghrib, 0, 5) : null,
            'isya'    => $prayer->isya    ? substr($prayer->isya,    0, 5) : null,
        ];
 
        $berikutnya = null;
        foreach ($waktuList as $nama => $waktu) {
            if ($waktu && $now < $waktu) {
                $berikutnya = $nama;
                break;
            }
        }
 
        return response()->json([
            'status'  => true,
            'message' => 'Jadwal sholat hari ini',
            'data'    => array_merge($this->prayerArray($prayer), [
                'berikutnya' => $berikutnya, // nama waktu sholat berikutnya (null jika isya sudah lewat)
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
 
        // Validasi format tanggal
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json([
                'status'  => false,
                'message' => 'Format tanggal tidak valid. Gunakan format YYYY-MM-DD.',
            ], 422);
        }
 
        $city   = $this->pesantrenCity();
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
    // MONTHLY — GET /api/pesantren/prayers/monthly
    // Jadwal sholat satu bulan penuh (opsional untuk kalender)
    // Query: month (1-12), year (YYYY)
    // ============================================================
    public function monthly(Request $request): JsonResponse
    {
        $this->ensureUstadz();
 
        $month = (int) $request->get('month', now($this->timezone())->month);
        $year  = (int) $request->get('year',  now($this->timezone())->year);
        $city  = $this->pesantrenCity();
 
        // Ambil semua yang sudah ada di DB untuk bulan ini
        $existing = Prayer::where('city', $city)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get()
            ->keyBy(fn($p) => $p->date->format('Y-m-d'));
 
        // Hitung hari dalam bulan
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $result      = [];
 
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
 
            if (isset($existing[$date])) {
                $result[] = $this->prayerArray($existing[$date]);
            } else {
                // Fetch dari API untuk hari yang belum ada
                $prayer = $this->fetchFromApi($date, $city);
                if ($prayer) {
                    $result[] = $this->prayerArray($prayer);
                }
            }
        }
 
        return response()->json([
            'status'  => true,
            'message' => "Jadwal sholat bulan {$month}/{$year}",
            'data'    => [
                'month'   => $month,
                'year'    => $year,
                'city'    => $city,
                'total'   => count($result),
                'prayers' => $result,
            ],
        ]);
    }
}
