<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prayer;
use Carbon\Carbon;

class SantriPrayerController extends Controller
{
    private function ensurePesantrenUser()
    {
        if (!auth()->check() || !in_array(auth()->user()->role, ['ustadz', 'santri'])) {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak'
            ], 403));
        }
    }

    private function ensureUstadz()
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus ustadz)'
            ], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    // helper untuk rapihin payload response
    private function payload(Prayer $p)
    {
        return [
            'id' => $p->id,
            'date' => $p->date,

            // sesuaikan nama kolom di tabel prayers kamu:
            'imsak' => $p->imsak ?? null,
            'subuh' => $p->subuh ?? null,
            'dzuhur' => $p->dzuhur ?? null,
            'ashar' => $p->ashar ?? null,
            'maghrib' => $p->maghrib ?? null,
            'isya' => $p->isya ?? null,

            'source' => $p->source ?? null, // optional kalau ada
            'created_at' => $p->created_at,
            'updated_at' => $p->updated_at,
        ];
    }

    public function today()
    {
        $this->ensurePesantrenUser();

        $date = Carbon::today()->toDateString();

        $prayer = Prayer::where('company_id', $this->companyId())
            ->whereDate('date', $date)
            ->first();

        return response()->json([
            'status' => true,
            'message' => "Jadwal sholat $date",
            'data' => $prayer ? $this->payload($prayer) : null
        ]);
    }

    public function byDate($date)
    {
        $this->ensurePesantrenUser();

        // validasi format date
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Format tanggal tidak valid (pakai YYYY-MM-DD)'
            ], 422);
        }

        $prayer = Prayer::where('company_id', $this->companyId())
            ->whereDate('date', $date)
            ->first();

        return response()->json([
            'status' => true,
            'message' => "Jadwal sholat $date",
            'data' => $prayer ? $this->payload($prayer) : null
        ]);
    }

    /**
     * OPTIONAL:
     * Sync / isi data prayers ke tabel (misal input manual atau dari API eksternal).
     * Aku buat versi "upsert" biar aman.
     */
    public function sync(Request $request)
    {
        $this->ensureUstadz();

        $validated = $request->validate([
            'date' => 'required|date',
            'imsak' => 'nullable|string|max:10',
            'subuh' => 'nullable|string|max:10',
            'dzuhur' => 'nullable|string|max:10',
            'ashar' => 'nullable|string|max:10',
            'maghrib' => 'nullable|string|max:10',
            'isya' => 'nullable|string|max:10',
            'source' => 'nullable|string|max:100',
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        $prayer = Prayer::updateOrCreate(
            [
                'company_id' => $this->companyId(),
                'date' => $date,
            ],
            [
                'imsak' => $validated['imsak'] ?? null,
                'subuh' => $validated['subuh'] ?? null,
                'dzuhur' => $validated['dzuhur'] ?? null,
                'ashar' => $validated['ashar'] ?? null,
                'maghrib' => $validated['maghrib'] ?? null,
                'isya' => $validated['isya'] ?? null,
                'source' => $validated['source'] ?? null,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Prayer times tersimpan',
            'data' => $this->payload($prayer)
        ]);
    }
}
