<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\User;

class PesantrenUstadzAttendanceController extends Controller
{
    public function checkIn(Request $request)
    {
        // ==========================
        // VALIDASI LOKASI
        // ==========================
        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        // ==========================
        // CEGAH DOUBLE CHECK-IN
        // ==========================
        $exists = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->first();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah check-in hari ini'
            ], 422);
        }

        // ==========================
        // SIMPAN MANUAL (AMAN)
        // ==========================
        $attendance = new Attendance();
        $attendance->user_id = auth()->id();
        $attendance->date = $today;
        $attendance->time_in = Carbon::now()->format('H:i:s');
        $attendance->latlon_in = $request->latitude . ',' . $request->longitude;
        $attendance->status = 'on_time';
        $attendance->save();

        return response()->json([
            'status' => true,
            'message' => 'Check-in berhasil',
            'attendance' => $attendance
        ], 200);
    }

    public function checkOut(Request $request)
    {
        // ==========================
        // VALIDASI LOKASI
        // ==========================
        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        // ==========================
        // AMBIL ABSEN HARI INI
        // ==========================
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'Belum check-in'
            ], 422);
        }

        // ==========================
        // CEGAH CHECKOUT GANDA
        // ==========================
        if ($attendance->time_out) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah check-out hari ini'
            ], 422);
        }

        // ==========================
        // SIMPAN CHECKOUT
        // ==========================
        $attendance->time_out = Carbon::now()->format('H:i:s');
        $attendance->latlon_out = $request->latitude . ',' . $request->longitude;
        $attendance->save();

        return response()->json([
            'status' => true,
            'message' => 'Check-out berhasil',
            'attendance' => $attendance
        ]);
    }

    // isCheckedIn
    public function isCheckedIn()
    {
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->first();

        return response()->json([
            'status' => true,
            'checked_in' => $attendance ? true : false,
            'checked_out' => $attendance && $attendance->time_out ? true : false,
            'attendance' => $attendance
        ]);
    }

    // =====================
    // LIST SANTRI TODAY
    // =====================
    public function santriToday()
    {
        $today = Carbon::today();

        $santri = User::where('role', 'santri')
            ->with(['attendances' => function ($q) use ($today) {
                $q->whereDate('date', $today);
            }])
            ->get()
            ->map(function ($s) {
                $attendance = $s->attendances->first();

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'status' => $attendance->status ?? 'absent'
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $santri
        ]);
    }

    // =====================
    // MARK SANTRI ATTENDANCE
    // =====================
    public function markSantriAttendance(Request $request)
    {
        $request->validate([
            'santri_id' => 'required|exists:users,id',
            'status' => 'required|in:on_time,permission,absent'
        ]);

        $today = Carbon::today();

        Attendance::updateOrCreate(
            [
                'user_id' => $request->santri_id,
                'date' => $today
            ],
            [
                'time_in' => Carbon::now()->format('H:i:s'),
                'status' => $request->status,
                'latlon_in' => '-'
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Absensi santri diperbarui'
        ]);
    }

    // =====================
    // SANTRI HISTORY
    // =====================
    public function santriHistory($id)
    {
        $history = Attendance::where('user_id', $id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $history
        ]);
    }
}
