<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Prayer;
use App\Models\Schedule;
use Carbon\Carbon;

class PesantrenDashboardController extends Controller
{
    public function ustadz(Request $request)
    {
        $today = Carbon::today();

        // =======================
        // PROFILE
        // =======================
        // get all table migration users
        $profile = auth()->user();

        // $profile = [
        //     'name' => auth()->user()->name,
        // ];

        // =======================
        // STATS SANTRI
        // =======================
        $santriIds = User::where('role', 'santri')->pluck('id');

        $totalSantri = $santriIds->count();

        $hadir = Attendance::whereDate('date', $today)
            ->whereIn('user_id', $santriIds)
            ->where('status', 'on_time')
            ->count();

        $sakit = Attendance::whereDate('date', $today)
            ->whereIn('user_id', $santriIds)
            ->where('status', 'permission')
            ->count();

        $izin = Permission::whereDate('date_permission', $today)
            ->where('is_approved', false)
            ->whereIn('user_id', $santriIds)
            ->count();

        // =======================
        // PRAYER TIME (TODAY)
        // =======================
        $prayer = Prayer::whereDate('date', $today)->first();

        $prayerTimes = $prayer ? [
            'subuh'   => $prayer->fajr,
            'dzuhur'  => $prayer->dzuhur,
            'ashar'   => $prayer->ashar,
            'maghrib' => $prayer->maghrib,
            'isya'    => $prayer->isya,
        ] : null;

        // =======================
        // TODAY SCHEDULES
        // =======================
        $schedules = Schedule::where('user_id', auth()->id())
            ->whereDate('start_datetime', $today)
            ->orderBy('start_datetime')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'title' => $schedule->title,
                    'start_time' => Carbon::parse($schedule->start_datetime)->format('H:i'),
                    'end_time' => Carbon::parse($schedule->start_datetime)->addMinutes(90)->format('H:i'),
                    'location' => $schedule->location['name'] ?? 'Ruang Kelas',
                    'santri_count' => 25 // dummy, nanti real dari relasi kelas
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Dashboard ustadz',
            'data' => [
                'profile' => $profile,
                'stats' => [
                    'total_santri' => $totalSantri,
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin' => $izin,
                ],
                'prayer_times' => $prayerTimes,
                'today_schedules' => $schedules
            ]
        ]);
    }
}
