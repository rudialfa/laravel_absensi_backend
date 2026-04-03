<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\MutabaahYaumiyah;
use App\Models\Note;
use App\Models\Permission;
use App\Models\Prayer;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PesantrenDashboardController extends Controller
{

    public function ustadz(Request $request): JsonResponse
    {
        /** @var User $ustadz */
        $ustadz  = Auth::user();
        $company = $ustadz->company;

        if (! $company) {
            return response()->json(['message' => 'Pesantren tidak ditemukan.'], 404);
        }

        $today     = Carbon::today();
        $nowTime   = Carbon::now()->format('H:i:s');
        $monthYear = ['year' => $today->year, 'month' => $today->month];

        return response()->json([
            'data' => [
                'pesantren'         => $this->pesantrenInfo($company),
                'ustadz'            => $this->ustadzProfile($ustadz),
                'absensi_hari_ini'  => $this->absensiHariIni($company, $ustadz, $today),
                'jadwal_sholat'     => $this->jadwalSholat($company, $today),
                'jadwal_hari_ini'   => $this->jadwalHariIni($company, $today),
                'mutabaah_hari_ini' => $this->mutabaahHariIni($company, $today),
                'izin_pending'      => $this->izinSantriPending($company),
                'catatan_terbaru'   => $this->catatanTerbaru($company),
                'ringkasan_bulanan' => $this->ringkasanBulanan($company, $monthYear),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Info Pesantren
    // ─────────────────────────────────────────────────────────

    private function pesantrenInfo(Company $company): array
    {
        return [
            'id'        => $company->id,
            'name'      => $company->name,
            'email'     => $company->email,
            'address'   => $company->address,
            'city'      => $company->city,
            'image_url' => $company->image_url,
            'timezone'  => $company->timezone,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Profil Ustadz
    // ─────────────────────────────────────────────────────────

    private function ustadzProfile(User $ustadz): array
    {
        // Cek apakah ustadz sudah absen hari ini
        $sudahAbsen = Attendance::where('user_id', $ustadz->id)
            ->whereDate('date', Carbon::today())
            ->exists();

        return [
            'id'          => $ustadz->id,
            'name'        => $ustadz->name,
            'email'       => $ustadz->email,
            'phone'       => $ustadz->phone,
            'position'    => $ustadz->position,
            'department'  => $ustadz->department,
            'image_url'   => $ustadz->image_url,
            'sudah_absen' => $sudahAbsen,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Absensi Hari Ini
    // ─────────────────────────────────────────────────────────

    private function absensiHariIni(Company $company, User $ustadz, Carbon $today): array
    {
        // Semua santri aktif di pesantren ini
        $totalSantri = User::where('company_id', $company->id)
            ->where('role', 'santri')
            ->count();

        // Santri yang sudah absen hari ini
        $hadirQuery = Attendance::whereHas(
            'user',
            fn($q) =>
            $q->where('company_id', $company->id)->where('role', 'santri')
        )
            ->whereDate('date', $today);

        $hadir   = (clone $hadirQuery)->whereIn('status', ['on_time', 'late'])->count();
        $telat   = (clone $hadirQuery)->where('status', 'late')->count();
        $izin    = (clone $hadirQuery)->where('status', 'permission')->count();
        $absent  = $totalSantri - $hadir - $izin;

        // Absensi ustadz sendiri hari ini
        $absensiUstadz = Attendance::where('user_id', $ustadz->id)
            ->whereDate('date', $today)
            ->first();

        return [
            'santri' => [
                'total'   => $totalSantri,
                'hadir'   => $hadir,
                'telat'   => $telat,
                'izin'    => $izin,
                'absent'  => max($absent, 0),
                'persen_hadir' => $totalSantri > 0
                    ? round(($hadir / $totalSantri) * 100, 1)
                    : 0,
            ],
            'saya' => $absensiUstadz ? [
                'status'   => $absensiUstadz->status,
                'time_in'  => $absensiUstadz->time_in,
                'time_out' => $absensiUstadz->time_out,
            ] : null,
        ];
    }




    // ─────────────────────────────────────────────────────────
    // SECTION: Jadwal Sholat Hari Ini
    // ─────────────────────────────────────────────────────────

    private function jadwalSholat(Company $company, Carbon $today): ?array
    {
        $prayer = Prayer::where('city', $company->city)
            ->where('date', $today->toDateString())
            ->first();

        if (! $prayer) {
            return null;
        }

        $now    = Carbon::now();
        $waktu  = ['fajr', 'dzuhur', 'ashar', 'maghrib', 'isya'];
        $label  = ['Subuh', 'Dzuhur', 'Ashar', 'Maghrib', 'Isya'];

        $berikutnya = null;
        foreach ($waktu as $i => $key) {
            if ($prayer->$key) {
                $waktuSholat = Carbon::parse($today->toDateString() . ' ' . $prayer->$key);
                if ($waktuSholat->isFuture()) {
                    $berikutnya = [
                        'nama'  => $label[$i],
                        'waktu' => $prayer->$key,
                        'sisa_menit' => $now->diffInMinutes($waktuSholat),
                    ];
                    break;
                }
            }
        }

        return [
            'tanggal'    => $today->toDateString(),
            'fajr'       => $prayer->fajr,
            'dzuhur'     => $prayer->dzuhur,
            'ashar'      => $prayer->ashar,
            'maghrib'    => $prayer->maghrib,
            'isya'       => $prayer->isya,
            'berikutnya' => $berikutnya,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Jadwal Kegiatan Hari Ini
    // ─────────────────────────────────────────────────────────

    private function jadwalHariIni(Company $company, Carbon $today): array
    {
        $jadwal = Schedule::where('company_id', $company->id)
            ->whereDate('start_datetime', $today)
            ->where('status', '!=', 'canceled')
            ->orderBy('start_datetime')
            ->limit(5)
            ->get(['id', 'title', 'description', 'type', 'start_datetime', 'end_datetime', 'status', 'location']);

        return [
            'total' => $jadwal->count(),
            'list'  => $jadwal->map(fn($j) => [
                'id'             => $j->id,
                'title'          => $j->title,
                'description'    => $j->description,
                'type'           => $j->type,
                'start_datetime' => $j->start_datetime,
                'end_datetime'   => $j->end_datetime,
                'status'         => $j->status,
                'location'       => $j->location,
            ])->values(),
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Mutaba'ah Hari Ini (Kartu Prestasi Ngaji)
    // ─────────────────────────────────────────────────────────

    private function mutabaahHariIni(Company $company, Carbon $today): array
    {
        // Total sesi ngaji hari ini (pagi + sore)
        $sesiHariIni = MutabaahYaumiyah::where('company_id', $company->id)
            ->where('tanggal', $today->toDateString())
            ->get();

        $totalSesi  = $sesiHariIni->count();
        $sudahParaf = $sesiHariIni->whereNotNull('signed_at')->count();
        $belumParaf = $totalSesi - $sudahParaf;

        // Jumlah santri yang sudah ngaji hari ini
        $santriNgajiCount = $sesiHariIni->unique('santri_id')->count();

        // Distribusi penilaian hari ini
        $distribusiNilai = $sesiHariIni->groupBy('keterangan')
            ->map(fn($group) => $group->count())
            ->toArray();

        // 5 sesi terbaru yang belum diparaf
        $belumParafList = MutabaahYaumiyah::with(['santri:id,name,image_url'])
            ->where('company_id', $company->id)
            ->where('tanggal', $today->toDateString())
            ->whereNull('signed_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($m) => [
                'id'         => $m->id,
                'santri'     => [
                    'id'        => $m->santri?->id,
                    'name'      => $m->santri?->name,
                    'image_url' => $m->santri?->image_url,
                ],
                'kitab'      => $m->kitab,
                'jilid'      => $m->jilid,
                'halaman'    => $m->halaman_dari . ($m->halaman_sampai ? '-' . $m->halaman_sampai : ''),
                'sesi'       => $m->sesi,
                'keterangan' => $m->keterangan,
                'is_lanjut'  => $m->is_lanjut,
            ]);

        return [
            'tanggal'          => $today->toDateString(),
            'total_sesi'       => $totalSesi,
            'sudah_paraf'      => $sudahParaf,
            'belum_paraf'      => $belumParaf,
            'santri_ngaji'     => $santriNgajiCount,
            'distribusi_nilai' => $distribusiNilai,
            'antrian_paraf'    => $belumParafList,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Izin Santri Pending
    // ─────────────────────────────────────────────────────────

    private function izinSantriPending(Company $company): array
    {
        $pending = Permission::with(['user:id,name,image_url'])
            ->where('company_id', $company->id)
            ->whereNull('is_approved')   // null = pending (setelah migrasi alter nullable)
            ->whereDate('date_permission', '>=', Carbon::today())
            ->orderBy('date_permission')
            ->limit(10)
            ->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'santri'          => [
                    'id'        => $p->user?->id,
                    'name'      => $p->user?->name,
                    'image_url' => $p->user?->image_url,
                ],
                'date_permission' => $p->date_permission,
                'reason'          => $p->reason,
                'image'           => $p->image,
                'created_at'      => $p->created_at,
            ]);

        return [
            'total' => Permission::where('company_id', $company->id)
                ->whereNull('is_approved')
                ->count(),
            'list' => $pending,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Catatan Terbaru yang Dikirim Ustadz
    // ─────────────────────────────────────────────────────────

    private function catatanTerbaru(Company $company): array
    {
        $notes = Note::with(['user:id,name,image_url'])
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'title'      => $n->title,
                'note'       => $n->note,
                'penerima'   => [
                    'id'        => $n->user?->id,
                    'name'      => $n->user?->name,
                    'image_url' => $n->user?->image_url,
                ],
                'created_at' => $n->created_at,
            ]);

        return [
            'total' => Note::where('company_id', $company->id)->count(),
            'list'  => $notes,
        ];
    }

    // ─────────────────────────────────────────────────────────
    // SECTION: Ringkasan Bulanan Santri
    // ─────────────────────────────────────────────────────────

    private function ringkasanBulanan(Company $company, array $monthYear): array
    {
        $year  = $monthYear['year'];
        $month = $monthYear['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $totalSantri = User::where('company_id', $company->id)
            ->where('role', 'santri')
            ->count();

        // Total hari masuk (on_time + late) dari semua santri bulan ini
        $totalHadir = Attendance::whereHas(
            'user',
            fn($q) =>
            $q->where('company_id', $company->id)->where('role', 'santri')
        )
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', ['on_time', 'late'])
            ->count();

        // Total sesi mutaba'ah bulan ini
        $totalMutabaah = MutabaahYaumiyah::where('company_id', $company->id)
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->count();

        // Santri yang paling banyak izin bulan ini
        $topIzin = Permission::select('user_id', DB::raw('COUNT(*) as total'))
            ->where('company_id', $company->id)
            ->whereBetween('date_permission', [$startDate, $endDate])
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(3)
            ->with('user:id,name,image_url')
            ->get()
            ->map(fn($p) => [
                'santri' => [
                    'id'        => $p->user?->id,
                    'name'      => $p->user?->name,
                    'image_url' => $p->user?->image_url,
                ],
                'total_izin' => $p->total,
            ]);

        // Santri dengan nilai mutaba'ah terbaik (A+, A, A-) bulan ini
        $santriTerbaik = MutabaahYaumiyah::select('santri_id', DB::raw('COUNT(*) as nilai_bagus'))
            ->where('company_id', $company->id)
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('keterangan', ['A+', 'A', 'A-'])
            ->groupBy('santri_id')
            ->orderByDesc('nilai_bagus')
            ->limit(3)
            ->with('santri:id,name,image_url')
            ->get()
            ->map(fn($m) => [
                'santri' => [
                    'id'        => $m->santri?->id,
                    'name'      => $m->santri?->name,
                    'image_url' => $m->santri?->image_url,
                ],
                'nilai_bagus' => $m->nilai_bagus,
            ]);

        return [
            'periode'          => Carbon::createFromDate($year, $month, 1)->translatedFormat('F Y'),
            'total_santri'     => $totalSantri,
            'total_hadir'      => $totalHadir,
            'total_mutabaah'   => $totalMutabaah,
            'rata_hadir_harian' => $totalSantri > 0
                ? round($totalHadir / max($endDate->diffInDays($startDate) + 1, 1), 1)
                : 0,
            'top_izin'         => $topIzin,
            'santri_terbaik'   => $santriTerbaik,
        ];
    }
}
