<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\MutabaahYaumiyah;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PesantrenMutabaahController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════
    // HELPER PRIVATE
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Ambil company_id dari user yang login.
     */
    private function companyId(): int
    {
        return auth()->user()->company_id;
    }

    /**
     * Base query — selalu scope ke company pesantren yang sedang login.
     */
    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return MutabaahYaumiyah::ofCompany($this->companyId())
            ->with([
                'santri:id,name,image_url',
                'ustadz:id,name',
                'penandatangan:id,name',
            ]);
    }

    /**
     * Pastikan santri milik company ini.
     */
    private function findSantri(int $santriId): User
    {
        return User::where('id', $santriId)
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->firstOrFail();
    }

    /**
     * Pastikan record mutabaah milik company ini.
     */
    private function findRecord(int $id): MutabaahYaumiyah
    {
        return MutabaahYaumiyah::ofCompany($this->companyId())
            ->findOrFail($id);
    }

    // ═══════════════════════════════════════════════════════════════════
    // INDEX — GET /api/pesantren/mutabaah
    // ═══════════════════════════════════════════════════════════════════

    /**
     * List semua record ngaji.
     *
     * Query params (semua opsional):
     *   ?santri_id=  — filter per santri
     *   ?ustadz_id=  — filter per ustadz
     *   ?kitab=      — iqro | quran
     *   ?jilid=      — 1–7
     *   ?sesi=       — pagi | sore
     *   ?tanggal=    — YYYY-MM-DD (satu hari)
     *   ?bulan=      — 1–12 (kombinasi dengan tahun)
     *   ?tahun=      — YYYY
     *   ?is_lanjut=  — 1 | 0
     *   ?belum_paraf=1 — hanya yang belum diparaf
     *   ?per_page=   — default 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        // Filter opsional
        if ($request->filled('santri_id')) {
            $query->ofSantri((int) $request->santri_id);
        }

        if ($request->filled('ustadz_id')) {
            $query->ofUstadz((int) $request->ustadz_id);
        }

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

        if ($request->boolean('belum_paraf')) {
            $query->belumParaf();
        }

        $records = $query
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json($records);
    }

    // ═══════════════════════════════════════════════════════════════════
    // TODAY — GET /api/pesantren/mutabaah/today
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Semua sesi ngaji hari ini di pesantren ini.
     * Dipakai ustadz untuk lihat siapa saja yang sudah/belum ngaji hari ini.
     */
    public function today(Request $request): JsonResponse
    {
        $records = $this->baseQuery()
            ->hariIni()
            ->when($request->filled('sesi'), fn($q) => $q->sesi($request->sesi))
            ->orderBy('sesi')
            ->orderBy('created_at')
            ->get();

        // Ringkasan hari ini
        $summary = [
            'total_sesi'    => $records->count(),
            'total_santri'  => $records->pluck('santri_id')->unique()->count(),
            'sudah_paraf'   => $records->whereNotNull('signed_by')->count(),
            'belum_paraf'   => $records->whereNull('signed_by')->count(),
            'lanjut'        => $records->where('is_lanjut', true)->count(),
            'ulang'         => $records->where('is_lanjut', false)->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'data'    => $records,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // STORE — POST /api/pesantren/mutabaah
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Catat sesi ngaji santri.
     *
     * Body:
     *   santri_id*     — ID santri
     *   kitab*         — iqro | quran
     *   jilid*         — 1–7
     *   halaman_dari*  — nomor halaman mulai
     *   halaman_sampai — nomor halaman selesai (opsional)
     *   tanggal*       — YYYY-MM-DD
     *   sesi*          — pagi | sore
     *   keterangan*    — A+, A, A-, B+, B, B-, C+, C, C-, D+, D, D-
     *   is_lanjut      — override otomatis (opsional, default dari keterangan)
     *   catatan        — teks bebas (opsional)
     */
    public function store(Request $request): JsonResponse
    {
        $ustadz     = auth()->user();
        $companyId  = $this->companyId();

        $validated = $request->validate([
            'santri_id'      => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'kitab'          => ['required', Rule::in(MutabaahYaumiyah::SEMUA_KITAB)],
            'jilid'          => ['required', 'integer', 'min:1', 'max:7'],
            'halaman_dari'   => ['required', 'integer', 'min:1'],
            'halaman_sampai' => ['nullable', 'integer', 'gte:halaman_dari'],
            'tanggal'        => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sesi'           => ['required', Rule::in(MutabaahYaumiyah::SEMUA_SESI)],
            'keterangan'     => ['required', Rule::in(MutabaahYaumiyah::SEMUA_NILAI)],
            'is_lanjut'      => ['nullable', 'boolean'],
            'catatan'        => ['nullable', 'string', 'max:500'],
        ], [
            'santri_id.exists'      => 'Santri tidak ditemukan di pesantren ini.',
            'sesi.in'               => 'Sesi hanya boleh: pagi atau sore.',
            'keterangan.in'         => 'Nilai tidak valid.',
            'tanggal.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
        ]);

        // Cek duplikat: santri + tanggal + sesi
        $sudahAda = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($validated['santri_id'])
            ->whereDate('tanggal', $validated['tanggal'])
            ->sesi($validated['sesi'])
            ->exists();

        if ($sudahAda) {
            return response()->json([
                'message' => "Santri sudah memiliki catatan ngaji sesi {$validated['sesi']} pada tanggal {$validated['tanggal']}.",
            ], 422);
        }

        $record = MutabaahYaumiyah::create([
            ...$validated,
            'company_id' => $companyId,
            'ustadz_id'  => $ustadz->id,
            // is_lanjut akan di-set otomatis via Model::booted()
            // kecuali jika request menyertakan is_lanjut secara eksplisit
        ]);

        $record->load(['santri:id,name,image_url', 'ustadz:id,name']);

        return response()->json([
            'message' => 'Sesi ngaji berhasil dicatat.',
            'data'    => $record,
        ], 201);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SHOW — GET /api/pesantren/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Detail satu sesi ngaji.
     */
    public function show(int $id): JsonResponse
    {
        $record = $this->baseQuery()->findOrFail($id);

        return response()->json(['data' => $record]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE — PUT /api/pesantren/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Koreksi sesi ngaji (nilai, halaman, catatan).
     * Hanya bisa diedit oleh ustadz yang mencatat ATAU ustadz di company yang sama.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $record    = $this->findRecord($id);
        $companyId = $this->companyId();

        $validated = $request->validate([
            'kitab'          => ['sometimes', Rule::in(MutabaahYaumiyah::SEMUA_KITAB)],
            'jilid'          => ['sometimes', 'integer', 'min:1', 'max:7'],
            'halaman_dari'   => ['sometimes', 'integer', 'min:1'],
            'halaman_sampai' => ['nullable', 'integer', 'gte:halaman_dari'],
            'sesi'           => ['sometimes', Rule::in(MutabaahYaumiyah::SEMUA_SESI)],
            'keterangan'     => ['sometimes', Rule::in(MutabaahYaumiyah::SEMUA_NILAI)],
            'is_lanjut'      => ['nullable', 'boolean'],
            'catatan'        => ['nullable', 'string', 'max:500'],
            'ustadz_id'      => ['sometimes', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
        ]);

        // Jika tanggal atau sesi berubah, cek duplikat lagi
        $newTanggal = $request->tanggal ?? $record->tanggal->format('Y-m-d');
        $newSesi    = $validated['sesi'] ?? $record->sesi;

        $duplikat = MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($record->santri_id)
            ->whereDate('tanggal', $newTanggal)
            ->sesi($newSesi)
            ->where('id', '!=', $id)
            ->exists();

        if ($duplikat) {
            return response()->json([
                'message' => "Sudah ada catatan ngaji sesi {$newSesi} pada tanggal {$newTanggal} untuk santri ini.",
            ], 422);
        }

        $record->update($validated);
        $record->load(['santri:id,name,image_url', 'ustadz:id,name', 'penandatangan:id,name']);

        return response()->json([
            'message' => 'Catatan ngaji berhasil diperbarui.',
            'data'    => $record,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // DESTROY — DELETE /api/pesantren/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Hapus sesi ngaji.
     */
    public function destroy(int $id): JsonResponse
    {
        $record = $this->findRecord($id);
        $record->delete();

        return response()->json(['message' => 'Catatan ngaji berhasil dihapus.']);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SIGN — POST /api/pesantren/mutabaah/{id}/sign
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Ustadz memberikan paraf pada sesi ngaji.
     * Nama ustadz akan ditampilkan di frontend dengan font brush/kaligrafi.
     *
     * Jika sesi sudah diparaf sebelumnya, endpoint ini akan mengganti paraf
     * (misalnya ustadz salah tap, bisa dikoreksi).
     */
    public function sign(int $id): JsonResponse
    {
        $ustadz = auth()->user();
        $record = $this->findRecord($id);

        $sudahDiparaf = $record->signed_by !== null;

        $record->update([
            'signed_by' => $ustadz->id,
            'signed_at' => now(),
        ]);

        $record->load('penandatangan:id,name');

        return response()->json([
            'message' => $sudahDiparaf
                ? "Paraf diperbarui oleh {$ustadz->name}."
                : "Paraf berhasil diberikan oleh {$ustadz->name}.",
            'data' => [
                'id'           => $record->id,
                'signed_by'    => $record->signed_by,
                'signed_at'    => $record->signed_at,
                'penandatangan' => $record->penandatangan,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SANTRI KARTU — GET /api/pesantren/mutabaah/santri/{santriId}
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Kartu prestasi lengkap per santri — semua jilid, semua riwayat.
     *
     * Query params (opsional):
     *   ?kitab=     — iqro | quran
     *   ?jilid=     — filter jilid tertentu
     *   ?bulan=     — 1–12
     *   ?tahun=     — YYYY
     *   ?per_page=  — default 30
     */
    public function santriKartu(Request $request, int $santriId): JsonResponse
    {
        $santri = $this->findSantri($santriId);

        $query = MutabaahYaumiyah::ofCompany($this->companyId())
            ->ofSantri($santriId)
            ->with(['ustadz:id,name', 'penandatangan:id,name']);

        if ($request->filled('kitab')) {
            $query->kitab($request->kitab);
        }

        if ($request->filled('jilid')) {
            $query->jilid((int) $request->jilid);
        }

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->bulan((int) $request->bulan, (int) $request->tahun);
        }

        $records = $query
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->paginate((int) $request->get('per_page', 30));

        // Ringkasan per jilid
        $ringkasanJilid = MutabaahYaumiyah::ofCompany($this->companyId())
            ->ofSantri($santriId)
            ->select('kitab', 'jilid', DB::raw('count(*) as total_sesi'), DB::raw('sum(is_lanjut) as total_lanjut'))
            ->groupBy('kitab', 'jilid')
            ->orderBy('kitab')
            ->orderBy('jilid')
            ->get();

        return response()->json([
            'santri'          => [
                'id'        => $santri->id,
                'name'      => $santri->name,
                'image_url' => $santri->image_url,
            ],
            'ringkasan_jilid' => $ringkasanJilid,
            'kartu'           => $records,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // SANTRI PROGRESS — GET /api/pesantren/mutabaah/santri/{santriId}/progress
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Posisi ngaji terakhir santri — untuk auto-fill form input sesi baru.
     *
     * Mengembalikan:
     *   - Posisi iqro terakhir (jilid & halaman berikutnya)
     *   - Posisi quran terakhir (jilid & halaman berikutnya)
     *   - Record lengkap sesi terakhir
     */
    public function santriProgress(int $santriId): JsonResponse
    {
        $santri = $this->findSantri($santriId);

        // Record terakhir per kitab
        $progressIqro = MutabaahYaumiyah::ofCompany($this->companyId())
            ->ofSantri($santriId)
            ->kitab('iqro')
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->first();

        $progressQuran = MutabaahYaumiyah::ofCompany($this->companyId())
            ->ofSantri($santriId)
            ->kitab('quran')
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->first();

        // Hitung halaman berikutnya
        $nextIqro = $progressIqro
            ? [
                'kitab'        => 'iqro',
                'jilid'        => $progressIqro->jilid,
                'halaman_dari' => MutabaahYaumiyah::halamanBerikutnya($progressIqro),
                'is_lanjut'    => $progressIqro->is_lanjut,
                'last_record'  => $progressIqro,
            ]
            : null;

        $nextQuran = $progressQuran
            ? [
                'kitab'        => 'quran',
                'jilid'        => $progressQuran->jilid,
                'halaman_dari' => MutabaahYaumiyah::halamanBerikutnya($progressQuran),
                'is_lanjut'    => $progressQuran->is_lanjut,
                'last_record'  => $progressQuran,
            ]
            : null;

        return response()->json([
            'santri' => [
                'id'        => $santri->id,
                'name'      => $santri->name,
                'image_url' => $santri->image_url,
            ],
            'progress' => [
                'iqro'  => $nextIqro,
                'quran' => $nextQuran,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // REKAP — GET /api/pesantren/mutabaah/rekap
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Rekap progress semua santri di pesantren ini.
     * Berguna untuk dashboard ustadz — lihat siapa masih di jilid berapa.
     *
     * Query params (opsional):
     *   ?kitab=   — iqro | quran (default: iqro)
     *   ?bulan=   — 1–12
     *   ?tahun=   — YYYY
     */
    public function rekap(Request $request): JsonResponse
    {
        $companyId = $this->companyId();
        $kitab     = $request->get('kitab', 'iqro');

        // Posisi terakhir tiap santri untuk kitab ini
        // Subquery: ambil id record terbaru per santri
        $subQuery = MutabaahYaumiyah::ofCompany($companyId)
            ->kitab($kitab)
            ->select('santri_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('santri_id');

        $rekapSantri = MutabaahYaumiyah::joinSub($subQuery, 'latest', function ($join) {
            $join->on('mutabaah_yaumiyahs.id', '=', 'latest.last_id');
        })
            ->with('santri:id,name,image_url')
            ->select('mutabaah_yaumiyahs.*')
            ->orderBy('jilid', 'desc')
            ->orderBy('halaman_dari', 'desc')
            ->get()
            ->map(fn($r) => [
                'santri'        => $r->santri,
                'posisi_terakhir' => $r->label_posisi,
                'kitab'         => $r->kitab,
                'jilid'         => $r->jilid,
                'halaman_dari'  => $r->halaman_dari,
                'halaman_sampai' => $r->halaman_sampai,
                'keterangan'    => $r->keterangan,
                'is_lanjut'     => $r->is_lanjut,
                'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($r),
                'tanggal_terakhir'   => $r->tanggal->format('Y-m-d'),
                'sudah_paraf'   => $r->sudah_diparaf,
            ]);

        // Ringkasan per jilid
        $perJilid = MutabaahYaumiyah::ofCompany($companyId)
            ->kitab($kitab)
            ->when(
                $request->filled('bulan') && $request->filled('tahun'),
                fn($q) => $q->bulan((int) $request->bulan, (int) $request->tahun)
            )
            ->select('jilid', DB::raw('count(distinct santri_id) as jumlah_santri'))
            ->groupBy('jilid')
            ->orderBy('jilid')
            ->pluck('jumlah_santri', 'jilid');

        return response()->json([
            'kitab'      => $kitab,
            'per_jilid'  => $perJilid,
            'santri'     => $rekapSantri,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // EXPORT — GET /api/pesantren/mutabaah/export
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Export kartu prestasi — placeholder untuk PDF generation.
     *
     * Query params:
     *   ?santri_id= — export kartu satu santri
     *   ?bulan=     — filter bulan
     *   ?tahun=     — filter tahun
     *
     * Implementasi PDF (misal pakai barryvdh/laravel-dompdf) bisa
     * ditambahkan di sini sesuai kebutuhan template kartu fisik.
     */
    public function export(Request $request): JsonResponse
    {
        // TODO: implementasi PDF export
        // Contoh: return PDF::loadView('exports.kartu-mutabaah', $data)->download('kartu.pdf');

        return response()->json([
            'message' => 'Fitur export PDF akan segera tersedia.',
        ], 501);
    }
}
