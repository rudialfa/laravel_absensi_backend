<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\DailyReport;
use App\Models\MutabaahYaumiyah;
use App\Models\PerformanceScore;
use App\Models\Permission;
use App\Models\Prayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class Santridashboardcontroller extends Controller
{
    private function ensureSantri(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus Santri)'], 403));
        }
    }

    private function timezone(): string
    {
        return auth()->user()->company?->timezone ?? 'Asia/Jakarta';
    }

    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$month] ?? (string) $month;
    }

    // ============================================================
    // DASHBOARD SANTRI
    // GET /api/pesantren/santri/dashboard
    // ============================================================
    public function index(): JsonResponse
    {
        $this->ensureSantri();

        $santri    = auth()->user();
        $company   = $santri->company;
        $companyId = $santri->company_id;
        $santriId  = $santri->id;
        $today     = Carbon::now($this->timezone())->toDateString();
        $bulanIni  = Carbon::now($this->timezone());

        // ── Absensi hari ini ──────────────────────────────────────
        $attendanceToday = Attendance::where('company_id', $companyId)
            ->where('user_id', $santriId)
            ->whereDate('date', $today)
            ->first();

        $nextAction = 'checkin';
        if ($attendanceToday?->time_in && !$attendanceToday?->time_out) $nextAction = 'checkout';
        if ($attendanceToday?->time_in && $attendanceToday?->time_out)  $nextAction = 'done';

        // ── Statistik absensi bulan ini ───────────────────────────
        $startBulan = $bulanIni->copy()->startOfMonth()->toDateString();
        $endBulan   = $bulanIni->copy()->endOfMonth()->toDateString();

        $attendancesBulan = Attendance::where('company_id', $companyId)
            ->where('user_id', $santriId)
            ->whereBetween('date', [$startBulan, $endBulan])
            ->get(['status', 'late_minutes']);

        // ── Izin pending milik sendiri ────────────────────────────
        $izinPending = Permission::where('company_id', $companyId)
            ->where('user_id', $santriId)
            ->whereNull('is_approved')
            ->count();

        // ── Laporan harian hari ini ───────────────────────────────
        $laporanHariIni = DailyReport::where('company_id', $companyId)
            ->where('user_id', $santriId)
            ->where('date', $today)
            ->first();

        // ── Mutabaah hari ini ─────────────────────────────────────
        $mutabaahHariIni = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->whereDate('tanggal', $today)
            ->with(['ustadz:id,name', 'penandatangan:id,name'])
            ->orderBy('sesi')
            ->get()
            ->map(fn($m) => [
                'id'          => $m->id,
                'sesi'        => $m->sesi,
                'label_posisi' => $m->label_posisi,
                'keterangan'  => $m->keterangan,
                'warna'       => $m->warna_keterangan,
                'is_lanjut'   => $m->is_lanjut,
                'sudah_paraf' => $m->sudah_diparaf,
                'ustadz'      => $m->ustadz?->name,
            ]);

        // ── Progress ngaji terakhir ───────────────────────────────
        $progressIqro = MutabaahYaumiyah::where('company_id', $companyId)
            ->where('santri_id', $santriId)
            ->kitab('iqro')
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // ── Skor performa bulan ini ───────────────────────────────
        $skorBulanIni = PerformanceScore::where('company_id', $companyId)
            ->where('user_id', $santriId)
            ->where('month', $bulanIni->month)
            ->where('year',  $bulanIni->year)
            ->first();

        // ── Jadwal sholat hari ini ────────────────────────────────
        $city   = $company?->city ?? 'Magelang';
        $prayer = Prayer::where('date', $today)->where('city', $city)->first();

        $prayerToday = $prayer ? [
            'fajr'    => $prayer->fajr    ? substr($prayer->fajr,    0, 5) : null,
            'dzuhur'  => $prayer->dzuhur  ? substr($prayer->dzuhur,  0, 5) : null,
            'ashar'   => $prayer->ashar   ? substr($prayer->ashar,   0, 5) : null,
            'maghrib' => $prayer->maghrib ? substr($prayer->maghrib, 0, 5) : null,
            'isya'    => $prayer->isya    ? substr($prayer->isya,    0, 5) : null,
        ] : null;

        // Tandai waktu sholat berikutnya
        $prayerBerikutnya = null;
        if ($prayerToday) {
            $now = Carbon::now($this->timezone())->format('H:i');
            foreach ($prayerToday as $nama => $waktu) {
                if ($waktu && $now < $waktu) {
                    $prayerBerikutnya = $nama;
                    break;
                }
            }
        }

        return response()->json([
            'status'  => true,
            'message' => 'Dashboard santri berhasil diambil',
            'data'    => [
                'today' => $today,

                // ── Info santri ──
                'santri' => [
                    'id'        => $santri->id,
                    'name'      => $santri->name,
                    'position'  => $santri->position,  // kamar
                    'department' => $santri->department, // kelas
                    'image_url' => $santri->image_url,
                    'has_face'  => !empty($santri->face_embedding),
                ],

                // ── Absensi hari ini ──
                'absensi_hari_ini' => [
                    'next_action' => $nextAction,
                    'time_in'     => $attendanceToday?->time_in,
                    'time_out'    => $attendanceToday?->time_out,
                    'status'      => $attendanceToday?->status,
                    'late_minutes' => (int) ($attendanceToday?->late_minutes ?? 0),
                ],

                // ── Statistik bulan ini ──
                'statistik_bulan_ini' => [
                    'bulan'        => $bulanIni->month,
                    'tahun'        => $bulanIni->year,
                    'label'        => $this->bulanLabel($bulanIni->month) . ' ' . $bulanIni->year,
                    'hadir'        => $attendancesBulan->whereIn('status', ['on_time', 'late'])->count(),
                    'terlambat'    => $attendancesBulan->where('status', 'late')->count(),
                    'alpha'        => $attendancesBulan->where('status', 'absent')->count(),
                    'izin_pending' => $izinPending,
                ],

                // ── Laporan harian ──
                'laporan_harian' => [
                    'submitted_morning' => $laporanHariIni !== null,
                    'submitted_evening' => $laporanHariIni?->achievement !== null,
                    'is_achieved'       => (bool) ($laporanHariIni?->is_achieved ?? false),
                    'report'            => $laporanHariIni,
                ],

                // ── Mutabaah ──
                'mutabaah_hari_ini' => [
                    'total_sesi'  => $mutabaahHariIni->count(),
                    'pagi_done'   => $mutabaahHariIni->where('sesi', 'pagi')->isNotEmpty(),
                    'sore_done'   => $mutabaahHariIni->where('sesi', 'sore')->isNotEmpty(),
                    'sesi'        => $mutabaahHariIni,
                ],

                // ── Progress ngaji ──
                'progress_ngaji' => $progressIqro ? [
                    'kitab'              => $progressIqro->kitab,
                    'jilid'              => $progressIqro->jilid,
                    'halaman_terakhir'   => $progressIqro->label_halaman,
                    'halaman_berikutnya' => MutabaahYaumiyah::halamanBerikutnya($progressIqro),
                    'keterangan'         => $progressIqro->keterangan,
                    'warna'              => $progressIqro->warna_keterangan,
                    'tanggal_terakhir'   => $progressIqro->tanggal->format('Y-m-d'),
                ] : null,

                // ── Skor performa ──
                'skor_bulan_ini' => $skorBulanIni ? [
                    'final_score'      => $skorBulanIni->final_score,
                    'achievement_rate' => $skorBulanIni->achievement_rate,
                    'total_targets'    => $skorBulanIni->total_targets,
                    'targets_achieved' => $skorBulanIni->targets_achieved,
                ] : null,

                // ── Jadwal sholat ──
                'prayers_today' => $prayerToday
                    ? array_merge($prayerToday, ['berikutnya' => $prayerBerikutnya])
                    : null,
            ],
        ]);
    }
}
