<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\MutabaahYaumiyah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Validation\Rule;

class SantriMutabaahController extends Controller
{
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
        return MutabaahYaumiyah::where('company_id', $this->santri()->company_id)
            ->where('santri_id', $this->santri()->id)
            ->with([
                'ustadz:id,name',
                'penandatangan:id,name',
            ]);
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri/mutabaah
    // Semua riwayat ngaji milik santri yang login (read-only)
    //
    // Query params (semua opsional):
    //   ?kitab=     — iqro | quran
    //   ?jilid=     — 1–7
    //   ?sesi=      — pagi | sore
    //   ?tanggal=   — YYYY-MM-DD (satu hari spesifik)
    //   ?bulan=     — 1–12 (kombinasi dengan tahun)
    //   ?tahun=     — YYYY
    //   ?is_lanjut= — 1 | 0
    //   ?per_page=  — default 20
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureSantri();

        $query = $this->baseQuery();

        if ($request->filled('kitab'))    $query->kitab($request->kitab);
        if ($request->filled('jilid'))    $query->jilid((int) $request->jilid);
        if ($request->filled('sesi'))     $query->sesi($request->sesi);

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

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat ngaji berhasil diambil',
            'data'    => $records,
        ]);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/mutabaah/{id}
    // Detail satu sesi ngaji milik santri yang login
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureSantri();

        $record = $this->baseQuery()->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail sesi ngaji',
            'data'    => array_merge($record->toArray(), [
                'label_posisi'     => $record->label_posisi,
                'label_halaman'    => $record->label_halaman,
                'label_sesi'       => $record->label_sesi,
                'sudah_diparaf'    => $record->sudah_diparaf,
                'warna_keterangan' => $record->warna_keterangan,
            ]),
        ]);
    }

    // ============================================================
    // PROGRESS — GET /api/pesantren/santri/mutabaah/progress
    // Posisi ngaji terkini santri yang login
    //
    // Mengembalikan:
    //   - Progress iqro : jilid & halaman terakhir + halaman berikutnya
    //   - Progress quran: jilid & halaman terakhir + halaman berikutnya
    //   - Statistik keseluruhan
    //   - Statistik bulan ini
    //   - Riwayat 7 hari terakhir
    // ============================================================
    public function progress(): JsonResponse
    {
        $this->ensureSantri();

        $santri    = $this->santri();
        $companyId = $santri->company_id;
        $santriId  = $santri->id;

        // ── Progress per kitab ────────────────────────────────────

        $lastIqro = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->kitab('iqro')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $lastQuran = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->kitab('quran')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // ── Statistik keseluruhan ─────────────────────────────────

        $stats = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->selectRaw('
                count(*)                    as total_sesi,
                sum(is_lanjut = 1)          as total_lanjut,
                sum(is_lanjut = 0)          as total_ulang,
                count(distinct tanggal)     as total_hari,
                count(distinct jilid)       as jilid_dijangkau
            ')
            ->first();

        // ── Statistik bulan ini ───────────────────────────────────

        $statsBulanIni = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->bulan(now()->month, now()->year)
            ->selectRaw('
                count(*)                as total_sesi,
                sum(is_lanjut = 1)      as total_lanjut,
                sum(is_lanjut = 0)      as total_ulang,
                count(distinct tanggal) as total_hari
            ')
            ->first();

        // ── Riwayat 7 hari terakhir ───────────────────────────────

        $riwayat7Hari = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->where('tanggal', '>=', now()->subDays(6)->toDateString())
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'tanggal'          => $r->tanggal->format('Y-m-d'),
                'sesi'             => $r->sesi,
                'label_sesi'       => $r->label_sesi,
                'label_posisi'     => $r->label_posisi,
                'keterangan'       => $r->keterangan,
                'warna_keterangan' => $r->warna_keterangan,
                'is_lanjut'        => $r->is_lanjut,
                'sudah_diparaf'    => $r->sudah_diparaf,
                'ustadz'           => $r->ustadz,
                'penandatangan'    => $r->penandatangan,
            ]);

        // ── Susun response ────────────────────────────────────────

        return response()->json([
            'status'  => true,
            'message' => 'Progress ngaji berhasil diambil',
            'data'    => [
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
                        'total_sesi'      => (int) ($stats->total_sesi      ?? 0),
                        'total_lanjut'    => (int) ($stats->total_lanjut    ?? 0),
                        'total_ulang'     => (int) ($stats->total_ulang     ?? 0),
                        'total_hari'      => (int) ($stats->total_hari      ?? 0),
                        'jilid_dijangkau' => (int) ($stats->jilid_dijangkau ?? 0),
                    ],
                    'bulan_ini' => [
                        'bulan'        => now()->month,
                        'tahun'        => now()->year,
                        'total_sesi'   => (int) ($statsBulanIni->total_sesi   ?? 0),
                        'total_lanjut' => (int) ($statsBulanIni->total_lanjut ?? 0),
                        'total_ulang'  => (int) ($statsBulanIni->total_ulang  ?? 0),
                        'total_hari'   => (int) ($statsBulanIni->total_hari   ?? 0),
                    ],
                ],

                'riwayat_7_hari' => $riwayat7Hari,
            ],
        ]);
    }

    // ============================================================
    // EXPORT — GET /api/pesantren/santri/mutabaah/export
    // Export kartu prestasi santri yang login dalam bentuk PDF
    //
    // Query params (opsional):
    //   ?kitab=  — iqro | quran
    //   ?bulan=  — 1–12
    //   ?tahun=  — YYYY
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureSantri();

        $query = $this->baseQuery();

        if ($request->filled('kitab'))                               $query->kitab($request->kitab);
        if ($request->filled('bulan') && $request->filled('tahun')) $query->bulan((int) $request->bulan, (int) $request->tahun);

        $records = $query->orderBy('tanggal', 'desc')->orderBy('sesi', 'desc')->get();

        // Ringkasan per jilid
        $ringkasanJilid = MutabaahYaumiyah::where('company_id', $this->santri()->company_id)
            ->where('santri_id', $this->santri()->id)
            ->select('kitab', 'jilid', DB::raw('count(*) as total_sesi'), DB::raw('sum(is_lanjut) as total_lanjut'))
            ->groupBy('kitab', 'jilid')
            ->orderBy('kitab')
            ->orderBy('jilid')
            ->get();

        $santri   = $this->santri();
        $fileName = 'kartu-ngaji-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.santri_mutabaah', [
            'santri'          => $santri,
            'company'         => $santri->company ?? (object)['name' => ''],
            'records'         => $records,
            'ringkasanJilid'  => $ringkasanJilid,
            'generatedAt'     => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
