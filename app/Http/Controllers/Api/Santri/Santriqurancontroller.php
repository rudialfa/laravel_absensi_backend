<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\MutabaahYaumiyah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Santriqurancontroller extends Controller
{
    private const API_BASE  = 'https://api.alquran.cloud/v1';
    private const CACHE_TTL = 60 * 60 * 24 * 7; // 7 hari

    private const EDITION_ARAB  = 'quran-uthmani';
    private const EDITION_LATIN = 'en.transliteration';
    private const EDITION_ID    = 'id.indonesian';
    private const EDITION_AUDIO = 'ar.alafasy';

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

    private function fetchCached(string $endpoint, string $cacheKey): ?array
    {
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($endpoint) {
            try {
                $response = Http::timeout(10)->get(self::API_BASE . $endpoint);
                if (!$response->successful()) return null;
                $data = $response->json();
                if (($data['code'] ?? 0) !== 200) return null;
                return $data['data'];
            } catch (\Exception $e) {
                return null;
            }
        });
    }

    // ============================================================
    // SURAH LIST — GET /api/pesantren/santri/quran/surah
    // Sama dengan ustadz — data publik
    // ============================================================
    public function surahList(): JsonResponse
    {
        $this->ensureSantri();

        $data = $this->fetchCached('/surah', 'quran:surah_list');

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Gagal mengambil daftar surah.',
            ], 503);
        }

        $surah = collect($data)->map(fn($s) => [
            'number'           => $s['number'],
            'name'             => $s['name'],
            'englishName'      => $s['englishName'],
            'englishNameTrans' => $s['englishNameTranslation'],
            'numberOfAyahs'    => $s['numberOfAyahs'],
            'revelationType'   => $s['revelationType'],
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Daftar surah Al-Quran',
            'data'    => ['total' => $surah->count(), 'surah' => $surah],
        ]);
    }

    // ============================================================
    // SURAH DETAIL — GET /api/pesantren/santri/quran/surah/{number}
    // ============================================================
    public function surahDetail(Request $request, int $number): JsonResponse
    {
        $this->ensureSantri();

        if ($number < 1 || $number > 114) {
            return response()->json([
                'status'  => false,
                'message' => 'Nomor surah tidak valid (1-114).',
            ], 422);
        }

        $withAudio = (bool) $request->get('with_audio', false);
        $editions  = $withAudio
            ? self::EDITION_ARAB . ',' . self::EDITION_ID . ',' . self::EDITION_AUDIO
            : self::EDITION_ARAB . ',' . self::EDITION_ID;

        $cacheKey = "quran:surah:{$number}:audio_{$withAudio}";
        $data     = $this->fetchCached("/surah/{$number}/editions/{$editions}", $cacheKey);

        if (!$data) {
            return response()->json(['status' => false, 'message' => "Gagal mengambil surah {$number}."], 503);
        }

        $arabData  = collect($data[0]['ayahs'] ?? []);
        $idData    = collect($data[1]['ayahs'] ?? []);
        $audioData = $withAudio ? collect($data[2]['ayahs'] ?? []) : collect([]);

        $ayahs = $arabData->map(function ($ayat, $i) use ($idData, $audioData, $withAudio) {
            $item = [
                'number'        => $ayat['number'],
                'numberInSurah' => $ayat['numberInSurah'],
                'juz'           => $ayat['juz'],
                'page'          => $ayat['page'],
                'text'          => $ayat['text'],
                'terjemahan'    => $idData[$i]['text'] ?? null,
            ];
            if ($withAudio) {
                $item['audio']          = $audioData[$i]['audio'] ?? null;
                $item['audioSecondary'] = $audioData[$i]['audioSecondary'] ?? [];
            }
            return $item;
        });

        $surahInfo = $data[0];

        return response()->json([
            'status'  => true,
            'message' => 'Detail surah',
            'data'    => [
                'number'           => $surahInfo['number'],
                'name'             => $surahInfo['name'],
                'englishName'      => $surahInfo['englishName'],
                'englishNameTrans' => $surahInfo['englishNameTranslation'],
                'numberOfAyahs'    => $surahInfo['numberOfAyahs'],
                'revelationType'   => $surahInfo['revelationType'],
                'ayahs'            => $ayahs,
            ],
        ]);
    }

    // ============================================================
    // AYAT — GET /api/pesantren/santri/quran/ayat/{surah}:{ayah}
    // ============================================================
    public function ayat(string $ref): JsonResponse
    {
        $this->ensureSantri();

        if (!preg_match('/^\d+:\d+$/', $ref)) {
            return response()->json([
                'status'  => false,
                'message' => 'Format tidak valid. Gunakan {surah}:{ayah} contoh: 2:255',
            ], 422);
        }

        $editions = implode(',', [self::EDITION_ARAB, self::EDITION_LATIN, self::EDITION_ID, self::EDITION_AUDIO]);
        $data     = $this->fetchCached("/ayah/{$ref}/editions/{$editions}", "quran:ayat:{$ref}");

        if (!$data) {
            return response()->json(['status' => false, 'message' => "Ayat {$ref} tidak ditemukan."], 503);
        }

        [$arabData, $latinData, $idData, $audioData] = $data;

        return response()->json([
            'status'  => true,
            'message' => "Ayat {$ref}",
            'data'    => [
                'number'         => $arabData['number'],
                'numberInSurah'  => $arabData['numberInSurah'],
                'juz'            => $arabData['juz'],
                'page'           => $arabData['page'],
                'surah'          => $arabData['surah'],
                'text'           => $arabData['text'],
                'latin'          => $latinData['text'],
                'terjemahan'     => $idData['text'],
                'audio'          => $audioData['audio'],
                'audioSecondary' => $audioData['audioSecondary'] ?? [],
            ],
        ]);
    }

    // ============================================================
    // HALAMAN — GET /api/pesantren/santri/quran/halaman/{page}
    // ============================================================
    public function halaman(int $page): JsonResponse
    {
        $this->ensureSantri();

        if ($page < 1 || $page > 604) {
            return response()->json(['status' => false, 'message' => 'Nomor halaman tidak valid (1-604).'], 422);
        }

        $editions = self::EDITION_ARAB . ',' . self::EDITION_ID;
        $data     = $this->fetchCached("/page/{$page}/editions/{$editions}", "quran:halaman:{$page}");

        if (!$data) {
            return response()->json(['status' => false, 'message' => "Halaman {$page} tidak tersedia."], 503);
        }

        $arabAyahs = collect($data[0]['ayahs'] ?? []);
        $idAyahs   = collect($data[1]['ayahs'] ?? []);

        $ayahs = $arabAyahs->map(fn($ayat, $i) => [
            'number'        => $ayat['number'],
            'numberInSurah' => $ayat['numberInSurah'],
            'juz'           => $ayat['juz'],
            'surah'         => $ayat['surah'],
            'text'          => $ayat['text'],
            'terjemahan'    => $idAyahs[$i]['text'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => "Halaman mushaf {$page}",
            'data'    => ['page' => $page, 'total' => $ayahs->count(), 'ayahs' => $ayahs],
        ]);
    }

    // ============================================================
    // PROGRESS SANTRI — GET /api/pesantren/santri/quran/progress
    // Progress ngaji Al-Quran santri yang login + ayat terakhir dibaca
    // UTAMA: Ini integrasi MutabaahYaumiyah + AlQuran Cloud untuk santri
    // ============================================================
    public function progress(): JsonResponse
    {
        $this->ensureSantri();

        $santri    = auth()->user();
        $companyId = $santri->company_id;
        $santriId  = $santri->id;

        // Sesi quran terakhir
        $lastQuran = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->where('kitab', 'quran')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->first();

        // Ayat terakhir dibaca dari API
        $ayatTerakhir = null;
        if ($lastQuran) {
            $halaman  = $lastQuran->halaman_dari;
            $editions = self::EDITION_ARAB . ',' . self::EDITION_ID . ',' . self::EDITION_AUDIO;
            $data     = $this->fetchCached("/page/{$halaman}/editions/{$editions}", "quran:halaman_full:{$halaman}");

            if ($data) {
                $arabAyahs = collect($data[0]['ayahs'] ?? []);
                $idAyahs   = collect($data[1]['ayahs'] ?? []);
                $audioAyahs = collect($data[2]['ayahs'] ?? []);

                $ayatTerakhir = [
                    'halaman'    => $halaman,
                    'juz'        => $arabAyahs->first()['juz'] ?? null,
                    'surah'      => $arabAyahs->first()['surah'] ?? null,
                    'total_ayat' => $arabAyahs->count(),
                    'ayahs'      => $arabAyahs->map(fn($a, $i) => [
                        'number'        => $a['number'],
                        'numberInSurah' => $a['numberInSurah'],
                        'text'          => $a['text'],
                        'terjemahan'    => $idAyahs[$i]['text']  ?? null,
                        'audio'         => $audioAyahs[$i]['audio'] ?? null,
                    ])->values(),
                ];
            }
        }

        // Statistik bulan ini
        $statsBulan = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->where('kitab', 'quran')
            ->whereMonth('tanggal', now()->month)
            ->whereYear('tanggal',  now()->year)
            ->selectRaw('count(*) as total, sum(is_lanjut=1) as lanjut, sum(is_lanjut=0) as ulang')
            ->first();

        return response()->json([
            'status'  => true,
            'message' => 'Progress ngaji Al-Quran',
            'data'    => [
                'santri' => [
                    'id'        => $santri->id,
                    'name'      => $santri->name,
                    'image_url' => $santri->image_url,
                ],

                'posisi_terakhir' => $lastQuran ? [
                    'tanggal'          => $lastQuran->tanggal->format('Y-m-d'),
                    'halaman_dari'     => $lastQuran->halaman_dari,
                    'halaman_sampai'   => $lastQuran->halaman_sampai,
                    'label_halaman'    => $lastQuran->label_halaman,
                    'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($lastQuran),
                    'keterangan'       => $lastQuran->keterangan,
                    'warna'            => $lastQuran->warna_keterangan,
                    'is_lanjut'        => $lastQuran->is_lanjut,
                    'sudah_diparaf'    => $lastQuran->sudah_diparaf,
                    'ustadz'           => $lastQuran->ustadz,
                ] : null,

                // Halaman terakhir beserta ayat-ayatnya (dari API)
                'ayat_terakhir' => $ayatTerakhir,

                'statistik_bulan_ini' => [
                    'bulan'  => now()->month,
                    'tahun'  => now()->year,
                    'total'  => (int) ($statsBulan->total  ?? 0),
                    'lanjut' => (int) ($statsBulan->lanjut ?? 0),
                    'ulang'  => (int) ($statsBulan->ulang  ?? 0),
                ],
            ],
        ]);
    }

    // ============================================================
    // SESI DETAIL — GET /api/pesantren/santri/quran/sesi/{id}
    // Detail satu sesi ngaji santri sendiri + tampilkan ayat yang dibaca
    // ============================================================
    public function sesiDetail(int $id): JsonResponse
    {
        $this->ensureSantri();

        $mutabaah = MutabaahYaumiyah::where('company_id', auth()->user()->company_id)
            ->where('santri_id', auth()->id())
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->findOrFail($id);

        $quranData = null;

        if ($mutabaah->kitab === 'quran') {
            $halaman  = $mutabaah->halaman_dari;
            $editions = self::EDITION_ARAB . ',' . self::EDITION_ID . ',' . self::EDITION_AUDIO;
            $data     = $this->fetchCached("/page/{$halaman}/editions/{$editions}", "quran:halaman_full:{$halaman}");

            if ($data) {
                $arabAyahs  = collect($data[0]['ayahs'] ?? []);
                $idAyahs    = collect($data[1]['ayahs'] ?? []);
                $audioAyahs = collect($data[2]['ayahs'] ?? []);

                $quranData = [
                    'halaman'    => $halaman,
                    'juz'        => $arabAyahs->first()['juz'] ?? null,
                    'surah'      => $arabAyahs->first()['surah'] ?? null,
                    'total_ayat' => $arabAyahs->count(),
                    'ayahs'      => $arabAyahs->map(fn($a, $i) => [
                        'number'        => $a['number'],
                        'numberInSurah' => $a['numberInSurah'],
                        'text'          => $a['text'],
                        'terjemahan'    => $idAyahs[$i]['text']     ?? null,
                        'audio'         => $audioAyahs[$i]['audio'] ?? null,
                    ])->values(),
                    'source' => 'alquran.cloud',
                ];
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Detail sesi ngaji',
            'data'    => [
                'id'             => $mutabaah->id,
                'tanggal'        => $mutabaah->tanggal->format('Y-m-d'),
                'sesi'           => $mutabaah->sesi,
                'label_sesi'     => $mutabaah->label_sesi,
                'kitab'          => $mutabaah->kitab,
                'jilid'          => $mutabaah->jilid,
                'halaman_dari'   => $mutabaah->halaman_dari,
                'halaman_sampai' => $mutabaah->halaman_sampai,
                'label_posisi'   => $mutabaah->label_posisi,
                'keterangan'     => $mutabaah->keterangan,
                'warna'          => $mutabaah->warna_keterangan,
                'is_lanjut'      => $mutabaah->is_lanjut,
                'catatan'        => $mutabaah->catatan,
                'sudah_diparaf'  => $mutabaah->sudah_diparaf,
                'ustadz'         => $mutabaah->ustadz,
                'penandatangan'  => $mutabaah->penandatangan,
                'quran_data'     => $quranData,
            ],
        ]);
    }
}
