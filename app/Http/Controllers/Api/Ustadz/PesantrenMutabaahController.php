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
    private function companyId(): int
    {
        return auth()->user()->company_id;
    }

    private function baseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return MutabaahYaumiyah::ofCompany($this->companyId())
            ->with([
                'santri:id,name,image_url',
                'ustadz:id,name',
                'penandatangan:id,name',
            ]);
    }

    private function findSantri(int $santriId): User
    {
        return User::where('id', $santriId)
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->firstOrFail();
    }

    private function findRecord(int $id): MutabaahYaumiyah
    {
        return MutabaahYaumiyah::ofCompany($this->companyId())
            ->findOrFail($id);
    }

    // ═══════════════════════════════════════════════════════════
    // INDEX — GET /api/pesantren/mutabaah
    // ═══════════════════════════════════════════════════════════

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($request->filled('santri_id'))  $query->ofSantri((int) $request->santri_id);
        if ($request->filled('ustadz_id'))  $query->ofUstadz((int) $request->ustadz_id);
        if ($request->filled('kitab'))      $query->kitab($request->kitab);
        if ($request->filled('jilid'))      $query->jilid((int) $request->jilid);
        if ($request->filled('sesi'))       $query->sesi($request->sesi);

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->tanggal);
        } elseif ($request->filled('bulan') && $request->filled('tahun')) {
            $query->bulan((int) $request->bulan, (int) $request->tahun);
        }

        if ($request->filled('is_lanjut'))  $query->where('is_lanjut', (bool) $request->is_lanjut);
        if ($request->boolean('belum_paraf')) $query->belumParaf();

        $records = $query
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json($records);
    }

    // ═══════════════════════════════════════════════════════════
    // TODAY — GET /api/pesantren/mutabaah/today
    // ═══════════════════════════════════════════════════════════

    public function today(Request $request): JsonResponse
    {
        $records = $this->baseQuery()
            ->hariIni()
            ->when($request->filled('sesi'), fn($q) => $q->sesi($request->sesi))
            ->orderBy('sesi')
            ->orderBy('created_at')
            ->get();

        $summary = [
            'total_sesi'   => $records->count(),
            'total_santri' => $records->pluck('santri_id')->unique()->count(),
            'sudah_paraf'  => $records->whereNotNull('signed_by')->count(),
            'belum_paraf'  => $records->whereNull('signed_by')->count(),
            'lanjut'       => $records->where('is_lanjut', true)->count(),
            'ulang'        => $records->where('is_lanjut', false)->count(),
        ];

        return response()->json(['summary' => $summary, 'data' => $records]);
    }

    // ═══════════════════════════════════════════════════════════
    // STORE — POST /api/pesantren/mutabaah
    // ═══════════════════════════════════════════════════════════

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->companyId();

        $validated = $request->validate([
            'santri_id'      => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'kitab'          => ['required', Rule::in(MutabaahYaumiyah::SEMUA_KITAB)],
            'jilid'          => ['required', 'integer', 'min:1', 'max:7'],
            'halaman_dari'   => ['required', 'integer', 'min:1'],
            'halaman_sampai' => ['nullable', 'integer', 'gte:halaman_dari'],
            'tanggal'        => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sesi'           => ['required', Rule::in(MutabaahYaumiyah::SEMUA_SESI)],
            'keterangan'     => ['required', Rule::in(MutabaahYaumiyah::SEMUA_NILAI)],
            'is_lanjut'      => ['nullable', 'boolean'],
            'catatan'        => ['nullable', 'string', 'max:500'],
        ], [
            'santri_id.exists'         => 'Santri tidak ditemukan di pesantren ini.',
            'sesi.in'                  => 'Sesi hanya boleh: pagi atau sore.',
            'keterangan.in'            => 'Nilai tidak valid.',
            'tanggal.before_or_equal'  => 'Tanggal tidak boleh di masa depan.',
        ]);

        // FIX: validasi 'date' dihapus (redundant & terkadang konflik dengan date_format)

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
            'ustadz_id'  => auth()->id(),
        ]);

        $record->load(['santri:id,name,image_url', 'ustadz:id,name']);

        return response()->json(['message' => 'Sesi ngaji berhasil dicatat.', 'data' => $record], 201);
    }

    // ═══════════════════════════════════════════════════════════
    // SHOW — GET /api/pesantren/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════

    public function show(int $id): JsonResponse
    {
        $record = $this->baseQuery()->findOrFail($id);
        return response()->json(['data' => $record]);
    }

    // ═══════════════════════════════════════════════════════════
    // UPDATE — PUT /api/pesantren/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════

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

        // FIX: gunakan carbon format agar konsisten, hindari $request->tanggal langsung
        $newTanggal = $record->tanggal->format('Y-m-d'); // tanggal tidak bisa diubah via update
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

        return response()->json(['message' => 'Catatan ngaji berhasil diperbarui.', 'data' => $record]);
    }

    // ═══════════════════════════════════════════════════════════
    // DESTROY — DELETE /api/pesantren/mutabaah/{id}
    // ═══════════════════════════════════════════════════════════

    public function destroy(int $id): JsonResponse
    {
        $this->findRecord($id)->delete();
        return response()->json(['message' => 'Catatan ngaji berhasil dihapus.']);
    }

    // ═══════════════════════════════════════════════════════════
    // SIGN — POST /api/pesantren/mutabaah/{id}/sign
    // ═══════════════════════════════════════════════════════════

    public function sign(int $id): JsonResponse
    {
        $ustadz       = auth()->user();
        $record       = $this->findRecord($id);
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
                'id'            => $record->id,
                'signed_by'     => $record->signed_by,
                'signed_at'     => $record->signed_at,
                'penandatangan' => $record->penandatangan,
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // SANTRI KARTU — GET /api/pesantren/mutabaah/santri/{santriId}
    // ═══════════════════════════════════════════════════════════

    public function santriKartu(Request $request, int $santriId): JsonResponse
    {
        $santri = $this->findSantri($santriId);

        $query = MutabaahYaumiyah::ofCompany($this->companyId())
            ->ofSantri($santriId)
            ->with(['ustadz:id,name', 'penandatangan:id,name']);

        if ($request->filled('kitab'))  $query->kitab($request->kitab);
        if ($request->filled('jilid'))  $query->jilid((int) $request->jilid);

        if ($request->filled('bulan') && $request->filled('tahun')) {
            $query->bulan((int) $request->bulan, (int) $request->tahun);
        }

        $records = $query
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->paginate((int) $request->get('per_page', 30));

        $ringkasanJilid = MutabaahYaumiyah::ofCompany($this->companyId())
            ->ofSantri($santriId)
            ->select('kitab', 'jilid', DB::raw('count(*) as total_sesi'), DB::raw('sum(is_lanjut) as total_lanjut'))
            ->groupBy('kitab', 'jilid')
            ->orderBy('kitab')
            ->orderBy('jilid')
            ->get();

        return response()->json([
            'santri'          => ['id' => $santri->id, 'name' => $santri->name, 'image_url' => $santri->image_url],
            'ringkasan_jilid' => $ringkasanJilid,
            'kartu'           => $records,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // SANTRI PROGRESS — GET /api/pesantren/mutabaah/santri/{santriId}/progress
    // ═══════════════════════════════════════════════════════════

    public function santriProgress(int $santriId): JsonResponse
    {
        $santri    = $this->findSantri($santriId);
        $companyId = $this->companyId();

        // FIX: ekstrak closure query agar tidak duplikasi kode
        $lastRecord = fn(string $kitab) => MutabaahYaumiyah::ofCompany($companyId)
            ->ofSantri($santriId)
            ->kitab($kitab)
            ->orderBy('tanggal', 'desc')
            ->orderBy('sesi', 'desc')
            ->orderBy('id', 'desc')
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->first();

        $progressIqro  = $lastRecord('iqro');
        $progressQuran = $lastRecord('quran');

        $toNext = fn($rec, string $kitab) => $rec ? [
            'kitab'        => $kitab,
            'jilid'        => $rec->jilid,
            'halaman_dari' => MutabaahYaumiyah::halamanBerikutnya($rec),
            'is_lanjut'    => $rec->is_lanjut,
            'last_record'  => $rec,
        ] : null;

        return response()->json([
            'santri'   => ['id' => $santri->id, 'name' => $santri->name, 'image_url' => $santri->image_url],
            'progress' => [
                'iqro'  => $toNext($progressIqro, 'iqro'),
                'quran' => $toNext($progressQuran, 'quran'),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // REKAP — GET /api/pesantren/mutabaah/rekap
    // ═══════════════════════════════════════════════════════════

    public function rekap(Request $request): JsonResponse
    {
        $companyId = $this->companyId();
        $kitab     = $request->get('kitab', 'iqro');

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
                'santri'              => $r->santri,
                'posisi_terakhir'     => $r->label_posisi,
                'kitab'               => $r->kitab,
                'jilid'               => $r->jilid,
                'halaman_dari'        => $r->halaman_dari,
                'halaman_sampai'      => $r->halaman_sampai,
                'keterangan'          => $r->keterangan,
                'is_lanjut'           => $r->is_lanjut,
                'halaman_berikutnya'  => MutabaahYaumiyah::halamanBerikutnya($r),
                'tanggal_terakhir'    => $r->tanggal->format('Y-m-d'),
                'sudah_paraf'         => $r->sudah_diparaf,
            ]);

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

        return response()->json(['kitab' => $kitab, 'per_jilid' => $perJilid, 'santri' => $rekapSantri]);
    }

    // ═══════════════════════════════════════════════════════════
    // EXPORT — GET /api/pesantren/mutabaah/export
    // ═══════════════════════════════════════════════════════════

    public function export(Request $request): JsonResponse
    {
        // TODO: implementasi PDF export (misal barryvdh/laravel-dompdf)
        return response()->json(['message' => 'Fitur export PDF akan segera tersedia.'], 501);
    }
}
