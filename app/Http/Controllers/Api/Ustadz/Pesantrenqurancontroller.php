<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\MutabaahYaumiyah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Pesantrenqurancontroller extends Controller
{
    private const API_BASE  = 'https://api.alquran.cloud/v1';
    private const CACHE_TTL = 60 * 60 * 24 * 7; // 7 hari

    // Edition yang dipakai
    private const EDITION_ARAB   = 'quran-uthmani';       // teks Arab standar
    private const EDITION_LATIN  = 'en.transliteration';  // transliterasi latin
    private const EDITION_ID     = 'id.indonesian';        // terjemahan Indonesia
    private const EDITION_AUDIO  = 'ar.alafasy';           // audio Mishary Alafasy

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

    /**
     * Fetch dari API dengan cache. Return null jika gagal.
     */
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

    /**
     * Bersihkan timing timezone dari string seperti "06:03 (WIB)"
     */
    private function cleanTime(?string $time): ?string
    {
        if (!$time) return null;
        return trim(preg_replace('/\s*\(.*?\)/', '', $time));
    }

    /**
     * Konversi nomor halaman mushaf ke surah:ayah terdekat.
     * Mushaf standar 604 halaman — mapping berdasarkan data umum.
     * Simplified: satu halaman ~15 ayat, tapi kita cukup beri info surah.
     */
    private function halamanKeInfo(int $halaman): array
    {
        // Halaman 1-2 = Al-Fatihah & Al-Baqarah awal
        // Mapping sederhana: setiap 20 halaman ≈ 1 juz
        $juz = min(30, (int) ceil($halaman / 20));
        return [
            'halaman' => $halaman,
            'juz_perkiraan' => $juz,
        ];
    }

    // ============================================================
    // SURAH LIST — GET /api/pesantren/quran/surah
    // List semua 114 surah (nama Arab + Indonesia + info)
    // Di-cache 7 hari — data ini tidak berubah
    // ============================================================
    public function surahList(): JsonResponse
    {
        $this->ensureUstadz();

        $data = $this->fetchCached('/surah', 'quran:surah_list');

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Gagal mengambil daftar surah. Periksa koneksi internet.',
            ], 503);
        }

        // Map ke format yang lebih ringkas
        $surah = collect($data)->map(fn($s) => [
            'number'           => $s['number'],
            'name'             => $s['name'],             // Arab
            'englishName'      => $s['englishName'],      // English
            'englishNameTrans' => $s['englishNameTranslation'], // Terjemahan nama
            'numberOfAyahs'    => $s['numberOfAyahs'],
            'revelationType'   => $s['revelationType'],   // Meccan | Medinan
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Daftar surah Al-Quran',
            'data'    => [
                'total'  => $surah->count(),
                'surah'  => $surah,
            ],
        ]);
    }

    // ============================================================
    // SURAH DETAIL — GET /api/pesantren/quran/surah/{number}
    // Isi surah lengkap: teks Arab + terjemahan Indonesia + audio
    // Query: ?with_audio=1 (default false, lebih berat)
    // ============================================================
    public function surahDetail(Request $request, int $number): JsonResponse
    {
        $this->ensureUstadz();

        if ($number < 1 || $number > 114) {
            return response()->json([
                'status'  => false,
                'message' => 'Nomor surah tidak valid (1-114).',
            ], 422);
        }

        $withAudio  = (bool) $request->get('with_audio', false);
        $editions   = $withAudio
            ? self::EDITION_ARAB . ',' . self::EDITION_ID . ',' . self::EDITION_AUDIO
            : self::EDITION_ARAB . ',' . self::EDITION_ID;

        $cacheKey = "quran:surah:{$number}:audio_{$withAudio}";
        $data     = $this->fetchCached("/surah/{$number}/editions/{$editions}", $cacheKey);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => "Gagal mengambil surah {$number}.",
            ], 503);
        }

        // data berisi array 2-3 edisi — kita zip menjadi per-ayat
        $arabData  = collect($data[0]['ayahs'] ?? []);
        $idData    = collect($data[1]['ayahs'] ?? []);
        $audioData = $withAudio ? collect($data[2]['ayahs'] ?? []) : collect([]);

        $ayahs = $arabData->map(function ($ayat, $index) use ($idData, $audioData, $withAudio) {
            $item = [
                'number'          => $ayat['number'],         // nomor global
                'numberInSurah'   => $ayat['numberInSurah'],  // nomor dalam surah
                'juz'             => $ayat['juz'],
                'page'            => $ayat['page'],            // halaman mushaf
                'text'            => $ayat['text'],            // teks Arab
                'terjemahan'      => $idData[$index]['text'] ?? null,
            ];
            if ($withAudio) {
                $item['audio'] = $audioData[$index]['audio'] ?? null;
                $item['audioSecondary'] = $audioData[$index]['audioSecondary'] ?? [];
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
    // AYAT — GET /api/pesantren/quran/ayat/{surah}:{ayah}
    // Satu ayat lengkap: Arab + latin + terjemahan + audio
    // Contoh: /api/pesantren/quran/ayat/2:255  (Ayat Kursi)
    // ============================================================
    public function ayat(string $ref): JsonResponse
    {
        $this->ensureUstadz();

        // Validasi format surah:ayah
        if (!preg_match('/^\d+:\d+$/', $ref)) {
            return response()->json([
                'status'  => false,
                'message' => 'Format referensi tidak valid. Gunakan format {surah}:{ayah} contoh: 2:255',
            ], 422);
        }

        $editions = implode(',', [
            self::EDITION_ARAB,
            self::EDITION_LATIN,
            self::EDITION_ID,
            self::EDITION_AUDIO,
        ]);

        $cacheKey = "quran:ayat:{$ref}";
        $data     = $this->fetchCached("/ayah/{$ref}/editions/{$editions}", $cacheKey);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => "Ayat {$ref} tidak ditemukan.",
            ], 503);
        }

        [$arabData, $latinData, $idData, $audioData] = $data;

        return response()->json([
            'status'  => true,
            'message' => "Ayat {$ref}",
            'data'    => [
                'number'        => $arabData['number'],
                'numberInSurah' => $arabData['numberInSurah'],
                'juz'           => $arabData['juz'],
                'page'          => $arabData['page'],
                'surah'         => [
                    'number'      => $arabData['surah']['number'],
                    'name'        => $arabData['surah']['name'],
                    'englishName' => $arabData['surah']['englishName'],
                ],
                'text'          => $arabData['text'],       // Arab
                'latin'         => $latinData['text'],      // Latin
                'terjemahan'    => $idData['text'],         // Indonesia
                'audio'         => $audioData['audio'],     // URL audio
                'audioSecondary' => $audioData['audioSecondary'] ?? [],
            ],
        ]);
    }

    // ============================================================
    // HALAMAN MUSHAF — GET /api/pesantren/quran/halaman/{page}
    // Semua ayat dalam satu halaman mushaf (1-604)
    // Berguna untuk sesi ngaji quran: tampilkan ayat yang dibaca santri
    // ============================================================
    public function halaman(int $page): JsonResponse
    {
        $this->ensureUstadz();

        if ($page < 1 || $page > 604) {
            return response()->json([
                'status'  => false,
                'message' => 'Nomor halaman tidak valid (1-604).',
            ], 422);
        }

        $editions = self::EDITION_ARAB . ',' . self::EDITION_ID;
        $cacheKey = "quran:halaman:{$page}";
        $data     = $this->fetchCached("/page/{$page}/editions/{$editions}", $cacheKey);

        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => "Halaman {$page} tidak tersedia.",
            ], 503);
        }

        $arabAyahs = collect($data[0]['ayahs'] ?? []);
        $idAyahs   = collect($data[1]['ayahs'] ?? []);

        $ayahs = $arabAyahs->map(fn($ayat, $i) => [
            'number'        => $ayat['number'],
            'numberInSurah' => $ayat['numberInSurah'],
            'juz'           => $ayat['juz'],
            'surah'         => [
                'number'      => $ayat['surah']['number'],
                'name'        => $ayat['surah']['name'],
                'englishName' => $ayat['surah']['englishName'],
            ],
            'text'        => $ayat['text'],
            'terjemahan'  => $idAyahs[$i]['text'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => "Halaman mushaf {$page}",
            'data'    => [
                'page'  => $page,
                'total' => $ayahs->count(),
                'ayahs' => $ayahs,
            ],
        ]);
    }

    // ============================================================
    // MUTABAAH + QURAN — GET /api/pesantren/quran/mutabaah/{id}
    // Detail satu sesi ngaji + tampilkan ayat/halaman yang dibaca santri
    //
    // Ini integrasi utama: MutabaahYaumiyah → AlQuran Cloud
    //
    // Jika kitab = 'quran':
    //   - Tampilkan halaman mushaf yang dibaca (halaman_dari s/d halaman_sampai)
    //   - Fetch ayat-ayat dari AlQuran Cloud API
    // Jika kitab = 'iqro':
    //   - Tidak ada data dari AlQuran Cloud, hanya info iqro
    // ============================================================
    public function mutabaahDetail(int $id): JsonResponse
    {
        $this->ensureUstadz();

        $mutabaah = MutabaahYaumiyah::where('company_id', auth()->user()->company_id)
            ->with(['santri:id,name,position,department,image_url', 'ustadz:id,name', 'penandatangan:id,name'])
            ->findOrFail($id);

        $result = [
            'id'           => $mutabaah->id,
            'tanggal'      => $mutabaah->tanggal->format('Y-m-d'),
            'sesi'         => $mutabaah->sesi,
            'label_sesi'   => $mutabaah->label_sesi,
            'kitab'        => $mutabaah->kitab,
            'jilid'        => $mutabaah->jilid,
            'halaman_dari' => $mutabaah->halaman_dari,
            'halaman_sampai' => $mutabaah->halaman_sampai,
            'label_posisi' => $mutabaah->label_posisi,
            'keterangan'   => $mutabaah->keterangan,
            'warna'        => $mutabaah->warna_keterangan,
            'is_lanjut'    => $mutabaah->is_lanjut,
            'catatan'      => $mutabaah->catatan,
            'sudah_diparaf' => $mutabaah->sudah_diparaf,
            'signed_at'    => $mutabaah->signed_at,
            'santri'       => $mutabaah->santri,
            'ustadz'       => $mutabaah->ustadz,
            'penandatangan' => $mutabaah->penandatangan,
            'quran_data'   => null, // akan diisi jika kitab = quran
        ];

        // ── Integrasi Al-Quran hanya untuk kitab quran ──────────────
        if ($mutabaah->kitab === 'quran') {
            $halamanDari    = $mutabaah->halaman_dari;
            $halamanSampai  = $mutabaah->halaman_sampai ?? $mutabaah->halaman_dari;

            // Fetch halaman yang dibaca (max 2 halaman per sesi)
            $pagesData = [];
            for ($page = $halamanDari; $page <= min($halamanSampai, $halamanDari + 1); $page++) {
                $editions = self::EDITION_ARAB . ',' . self::EDITION_ID;
                $cacheKey = "quran:halaman:{$page}";
                $pageData = $this->fetchCached("/page/{$page}/editions/{$editions}", $cacheKey);

                if ($pageData) {
                    $arabAyahs = collect($pageData[0]['ayahs'] ?? []);
                    $idAyahs   = collect($pageData[1]['ayahs'] ?? []);

                    $pagesData[] = [
                        'page'  => $page,
                        'ayahs' => $arabAyahs->map(fn($ayat, $i) => [
                            'number'        => $ayat['number'],
                            'numberInSurah' => $ayat['numberInSurah'],
                            'juz'           => $ayat['juz'],
                            'surah'         => [
                                'number'      => $ayat['surah']['number'],
                                'name'        => $ayat['surah']['name'],
                                'englishName' => $ayat['surah']['englishName'],
                            ],
                            'text'       => $ayat['text'],
                            'terjemahan' => $idAyahs[$i]['text'] ?? null,
                        ])->values(),
                    ];
                }
            }

            $result['quran_data'] = [
                'halaman_dari'   => $halamanDari,
                'halaman_sampai' => $halamanSampai,
                'pages'          => $pagesData,
                'source'         => 'alquran.cloud',
            ];
        }

        return response()->json([
            'status'  => true,
            'message' => 'Detail sesi ngaji',
            'data'    => $result,
        ]);
    }

    // ============================================================
    // KARTU SANTRI + QURAN — GET /api/pesantren/quran/santri/{santriId}/kartu
    // Progress ngaji santri + ayat/halaman terakhir yang dibaca
    // Berguna untuk: cetak kartu ngaji, tampilan progress di UI
    // ============================================================
    public function kartuSantri(int $santriId): JsonResponse
    {
        $this->ensureUstadz();

        $companyId = auth()->user()->company_id;

        // Ambil sesi quran terakhir santri ini
        $lastQuran = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->where('kitab', 'quran')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // Ambil sesi iqro terakhir
        $lastIqro = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->where('kitab', 'iqro')
            ->with(['ustadz:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // Fetch ayat terakhir yang dibaca jika ada
        $ayatTerakhir = null;
        if ($lastQuran) {
            $halaman  = $lastQuran->halaman_dari;
            $cacheKey = "quran:halaman:{$halaman}";
            $editions = self::EDITION_ARAB . ',' . self::EDITION_ID;
            $pageData = $this->fetchCached("/page/{$halaman}/editions/{$editions}", $cacheKey);

            if ($pageData) {
                $arabAyahs = collect($pageData[0]['ayahs'] ?? []);
                $idAyahs   = collect($pageData[1]['ayahs'] ?? []);

                // Ambil ayat pertama di halaman ini sebagai referensi posisi
                $firstAyat = $arabAyahs->first();
                if ($firstAyat) {
                    $ayatTerakhir = [
                        'halaman'       => $halaman,
                        'juz'           => $firstAyat['juz'],
                        'surah'         => $firstAyat['surah'],
                        'ayat_mulai'    => $firstAyat['numberInSurah'],
                        'total_ayat'    => $arabAyahs->count(),
                        'teks_pertama'  => $firstAyat['text'],
                        'terjemahan_pertama' => $idAyahs->first()['text'] ?? null,
                    ];
                }
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Kartu ngaji santri',
            'data'    => [
                'santri_id' => $santriId,

                'progress_quran' => $lastQuran ? [
                    'tanggal'      => $lastQuran->tanggal->format('Y-m-d'),
                    'halaman'      => $lastQuran->label_halaman,
                    'halaman_dari' => $lastQuran->halaman_dari,
                    'halaman_sampai' => $lastQuran->halaman_sampai,
                    'keterangan'   => $lastQuran->keterangan,
                    'is_lanjut'    => $lastQuran->is_lanjut,
                    'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($lastQuran),
                    'ustadz'       => $lastQuran->ustadz,
                    'sudah_diparaf' => $lastQuran->sudah_diparaf,
                ] : null,

                'progress_iqro' => $lastIqro ? [
                    'tanggal'      => $lastIqro->tanggal->format('Y-m-d'),
                    'jilid'        => $lastIqro->jilid,
                    'halaman'      => $lastIqro->label_halaman,
                    'keterangan'   => $lastIqro->keterangan,
                    'is_lanjut'    => $lastIqro->is_lanjut,
                    'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($lastIqro),
                    'ustadz'       => $lastIqro->ustadz,
                ] : null,

                // Posisi terakhir baca Al-Quran dari API
                'ayat_terakhir' => $ayatTerakhir,
            ],
        ]);
    }

    // ============================================================
    // SEARCH — GET /api/pesantren/quran/search
    // Cari ayat berdasarkan kata kunci (terjemahan Indonesia)
    // Query: ?q=sabar&surah=2 (surah opsional)
    // ============================================================
    public function search(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $keyword = trim($request->get('q', ''));
        if (strlen($keyword) < 3) {
            return response()->json([
                'status'  => false,
                'message' => 'Kata kunci minimal 3 karakter.',
            ], 422);
        }

        $surah    = $request->filled('surah') ? (int) $request->surah : null;
        $scope    = $surah ? $surah : 'all';
        $cacheKey = "quran:search:" . md5($keyword . ':' . $scope);

        $data = $this->fetchCached(
            '/search/' . urlencode($keyword) . '/' . $scope . '/' . self::EDITION_ID,
            $cacheKey
        );

        if (!$data) {
            return response()->json([
                'status'  => true,
                'message' => 'Tidak ada hasil ditemukan.',
                'data'    => ['total' => 0, 'matches' => []],
            ]);
        }

        $matches = collect($data['matches'] ?? [])->map(fn($m) => [
            'number'        => $m['number'],
            'numberInSurah' => $m['numberInSurah'],
            'surah'         => [
                'number'      => $m['surah']['number'],
                'name'        => $m['surah']['name'],
                'englishName' => $m['surah']['englishName'],
            ],
            'juz'        => $m['juz'],
            'page'       => $m['page'],
            'terjemahan' => $m['text'],
            'ref'        => $m['surah']['number'] . ':' . $m['numberInSurah'],
        ]);

        return response()->json([
            'status'  => true,
            'message' => "Hasil pencarian: \"{$keyword}\"",
            'data'    => [
                'keyword' => $keyword,
                'total'   => $data['count'] ?? $matches->count(),
                'matches' => $matches,
            ],
        ]);
    }

    // ============================================================
    // SANTRI — GET /api/pesantren/quran/santri/{santriId}/halaman-dibaca
    // Ambil semua halaman Al-Quran yang pernah dibaca santri
    // Berguna untuk kalender/tracker progress
    // ============================================================
    public function halamanDibaca(int $santriId): JsonResponse
    {
        $this->ensureUstadz();

        $companyId = auth()->user()->company_id;

        $records = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->where('kitab', 'quran')
            ->orderBy('tanggal', 'desc')
            ->get(['id', 'tanggal', 'sesi', 'halaman_dari', 'halaman_sampai', 'keterangan', 'is_lanjut', 'signed_by'])
            ->map(fn($r) => [
                'id'             => $r->id,
                'tanggal'        => $r->tanggal->format('Y-m-d'),
                'sesi'           => $r->sesi,
                'halaman_dari'   => $r->halaman_dari,
                'halaman_sampai' => $r->halaman_sampai ?? $r->halaman_dari,
                'keterangan'     => $r->keterangan,
                'is_lanjut'      => $r->is_lanjut,
                'sudah_diparaf'  => !is_null($r->signed_by),
            ]);

        // Range halaman yang sudah dibaca
        $halamanDibaca = $records->flatMap(function ($r) {
            return range($r['halaman_dari'], $r['halaman_sampai']);
        })->unique()->sort()->values();

        $maxHalaman = $halamanDibaca->max() ?? 0;

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat halaman Al-Quran santri',
            'data'    => [
                'santri_id'         => $santriId,
                'total_sesi'        => $records->count(),
                'halaman_tertinggi' => $maxHalaman,
                'juz_perkiraan'     => min(30, (int) ceil($maxHalaman / 20)),
                'total_halaman_unique' => $halamanDibaca->count(),
                'halaman_dibaca'    => $halamanDibaca,
                'riwayat'           => $records,
            ],
        ]);
    }
}
