<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\MutabaahYaumiyah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Validation\Rule;

class SantriMutabaahController extends Controller
{
      // ═══════════════════════════════════════════════════════════════════
    // HELPER PRIVATE
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Santri yang sedang login.
     */
    private function santri(): \App\Models\User
    {
        return auth()->user();
    }

    /**
     * Base query — selalu scope ke santri & company yang login.
     * Santri hanya bisa melihat data milik dirinya sendiri.
     */
    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return MutabaahYaumiyah::ofCompany($this->santri()->company_id)
            ->ofSantri($this->santri()->id)
            ->with([
                'ustadz:id,name',
                'penandatangan:id,name',
            ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // INDEX — GET /api/pesantren/santri/mutabaah
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Semua riwayat ngaji milik santri yang login.
     *
     * Query params (semua opsional):
     *   ?kitab=    — iqro | quran
     *   ?jilid=    — 1–7
     *   ?sesi=     — pagi | sore
     *   ?bulan=    — 1–12 (kombinasi dengan tahun)
     *   ?tahun=    — YYYY
     *   ?tanggal=  — YYYY-MM-DD (satu hari spesifik)
     *   ?is_lanjut= — 1 | 0
     *   ?per_page= — default 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($request->filled('kitab')) {
            $query->kitab($request->kitab);
        }

        if ($request->filled('jilid')) {
            $query->jilid((int) $request->jilid);
        }

        if ($request->filled('sesi')) {
            $query->sesi($request->sesi);
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } elseif ($request->filled('bulan') && $request->filled('tahun')) {
            $query->bulan((int) $request->bulan, (int) $request->tahun);
        }

        if ($request->filled('is_lanjut')) {
            $query->where('is_lanjut', (bool) $request->is_lanjut);
        }

        $records = $query
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json($records);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SHOW — GET /api/pesantren/santri/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detail satu sesi ngaji milik santri yang login.
     * Santri tidak bisa mengakses record milik santri lain.
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->baseQuery()->findOrFail($id);

        return response()->json([
            'data' => array_merge($record->toArray(), [
                'label_posisi'      => $record->label_posisi,
                'label_halaman'     => $record->label_halaman,
                'label_sesi'        => $record->label_sesi,
                'sudah_diparaf'     => $record->sudah_diparaf,
                'warna_keterangan'  => $record->warna_keterangan,
            ]),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // PROGRESS — GET /api/pesantren/santri/mutabaah/progress
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Posisi ngaji terkini santri yang login.
     *
     * Mengembalikan:
     *   - Progress iqro: jilid & halaman terakhir + halaman berikutnya
     *   - Progress quran: jilid & halaman terakhir + halaman berikutnya
     *   - Statistik keseluruhan: total sesi, lanjut, ulang, streak hari ini
     *   - Riwayat 7 hari terakhir
     */
    public function progress(): JsonResponse
    {
        $santri    = $this->santri();
        $companyId = $santri->company_id;
        $santriId  = $santri->id;

        // ── Progress per kitab ────────────────────────────────────────

        $lastIqro = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($santriId)
            ->kitab('iqro')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $lastQuran = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($santriId)
            ->kitab('quran')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // ── Statistik keseluruhan ─────────────────────────────────────

        $stats = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($santriId)
            ->selectRaw('
                count(*)                    as total_sesi,
                sum(is_lanjut = 1)          as total_lanjut,
                sum(is_lanjut = 0)          as total_ulang,
                count(distinct tanggal)     as total_hari,
                count(distinct jilid)       as jilid_dijangkau
            ')
            ->first();

        // ── Statistik bulan ini ───────────────────────────────────────

        $statsBulanIni = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($santriId)
            ->bulan(now()->month, now()->year)
            ->selectRaw('
                count(*)                as total_sesi,
                sum(is_lanjut = 1)      as total_lanjut,
                sum(is_lanjut = 0)      as total_ulang,
                count(distinct tanggal) as total_hari
            ')
            ->first();

        // ── Riwayat 7 hari terakhir ───────────────────────────────────

        $riwayat7Hari = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($santriId)
            ->where('tanggal', '>=', now()->subDays(6)->toDateString())
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->get()
            ->map(fn($r) => [
                'id'                => $r->id,
                'tanggal'           => $r->tanggal->format('Y-m-d'),
                'sesi'              => $r->sesi,
                'label_sesi'        => $r->label_sesi,
                'label_posisi'      => $r->label_posisi,
                'keterangan'        => $r->keterangan,
                'warna_keterangan'  => $r->warna_keterangan,
                'is_lanjut'         => $r->is_lanjut,
                'sudah_diparaf'     => $r->sudah_diparaf,
                'ustadz'            => $r->ustadz,
                'penandatangan'     => $r->penandatangan,
            ]);

        // ── Susun response ────────────────────────────────────────────

        return response()->json([
            'santri' => [
                'id'        => $santri->id,
                'name'      => $santri->name,
                'image_url' => $santri->image_url,
            ],

            'progress' => [
                'iqro' => $lastIqro ? [
                    'kitab'              => 'iqro',
                    'jilid'              => $lastIqro->jilid,
                    'halaman_terakhir'   => $lastIqro->label_halaman,
                    'halaman_dari'       => $lastIqro->halaman_dari,
                    'halaman_sampai'     => $lastIqro->halaman_sampai,
                    'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($lastIqro),
                    'keterangan'         => $lastIqro->keterangan,
                    'warna_keterangan'   => $lastIqro->warna_keterangan,
                    'is_lanjut'          => $lastIqro->is_lanjut,
                    'tanggal_terakhir'   => $lastIqro->tanggal->format('Y-m-d'),
                    'ustadz'             => $lastIqro->ustadz,
                    'sudah_diparaf'      => $lastIqro->sudah_diparaf,
                    'penandatangan'      => $lastIqro->penandatangan,
                ] : null,

                'quran' => $lastQuran ? [
                    'kitab'              => 'quran',
                    'jilid'              => $lastQuran->jilid,
                    'halaman_terakhir'   => $lastQuran->label_halaman,
                    'halaman_dari'       => $lastQuran->halaman_dari,
                    'halaman_sampai'     => $lastQuran->halaman_sampai,
                    'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($lastQuran),
                    'keterangan'         => $lastQuran->keterangan,
                    'warna_keterangan'   => $lastQuran->warna_keterangan,
                    'is_lanjut'          => $lastQuran->is_lanjut,
                    'tanggal_terakhir'   => $lastQuran->tanggal->format('Y-m-d'),
                    'ustadz'             => $lastQuran->ustadz,
                    'sudah_diparaf'      => $lastQuran->sudah_diparaf,
                    'penandatangan'      => $lastQuran->penandatangan,
                ] : null,
            ],

            'statistik' => [
                'keseluruhan' => [
                    'total_sesi'       => (int) ($stats->total_sesi ?? 0),
                    'total_lanjut'     => (int) ($stats->total_lanjut ?? 0),
                    'total_ulang'      => (int) ($stats->total_ulang ?? 0),
                    'total_hari'       => (int) ($stats->total_hari ?? 0),
                    'jilid_dijangkau'  => (int) ($stats->jilid_dijangkau ?? 0),
                ],
                'bulan_ini' => [
                    'bulan'        => now()->month,
                    'tahun'        => now()->year,
                    'total_sesi'   => (int) ($statsBulanIni->total_sesi ?? 0),
                    'total_lanjut' => (int) ($statsBulanIni->total_lanjut ?? 0),
                    'total_ulang'  => (int) ($statsBulanIni->total_ulang ?? 0),
                    'total_hari'   => (int) ($statsBulanIni->total_hari ?? 0),
                ],
            ],

            'riwayat_7_hari' => $riwayat7Hari,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXPORT — GET /api/pesantren/santri/mutabaah/export
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Export kartu prestasi santri yang login dalam bentuk PDF.
     *
     * Query params (opsional):
     *   ?kitab=  — iqro | quran (default: semua)
     *   ?bulan=  — 1–12
     *   ?tahun=  — YYYY
     *
     * Implementasi PDF bisa ditambahkan sesuai template kartu fisik
     * menggunakan barryvdh/laravel-dompdf atau library sejenis.
     */
    public function export(Request $request): JsonResponse
    {
        // TODO: implementasi PDF export
        // Contoh:
        // $data  = $this->baseQuery()->...->get();
        // return PDF::loadView('exports.kartu-santri', compact('data', 'santri'))->download('kartu.pdf');

        return response()->json([
            'message' => 'Fitur export PDF akan segera tersedia.',
        ], 501);
    }
}
