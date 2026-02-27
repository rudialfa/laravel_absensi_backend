<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;


class AttendanceController extends Controller
{

    public function index(Request $request)
    {
        $attendances = Attendance::where('user_id', $request->user()->id)
            ->orderBy('date', 'desc')
            ->paginate(15);

        return response()->json($attendances);
    }

    /**
     * CHECK-IN
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'latlon' => 'required|string'
        ]);

        $user = $request->user();
        $today = Carbon::today()->toDateString();

        // cek apakah sudah check-in hari ini
        $already = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if ($already) {
            return response()->json([
                'message' => 'Anda sudah check-in hari ini'
            ], 400);
        }

        $attendance = Attendance::create([
            'user_id'    => $user->id,
            'date'       => $today,
            'time_in'    => Carbon::now()->toTimeString(),
            'latlon_in'  => $request->latlon,
            'status'     => 'on_time'
        ]);

        return response()->json([
            'message' => 'Check-in berhasil',
            'data'    => $attendance
        ]);
    }

    /**
     * CHECK-OUT
     */
    public function checkOut(Request $request)
    {
        $request->validate([
            'latlon' => 'required|string'
        ]);

        $user = $request->user();
        $today = Carbon::today()->toDateString();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        if (!$attendance) {
            return response()->json([
                'message' => 'Anda belum check-in hari ini'
            ], 400);
        }

        if ($attendance->time_out) {
            return response()->json([
                'message' => 'Anda sudah check-out'
            ], 400);
        }

        $attendance->update([
            'time_out'   => Carbon::now()->toTimeString(),
            'latlon_out' => $request->latlon
        ]);

        return response()->json([
            'message' => 'Check-out berhasil',
            'data'    => $attendance
        ]);
    }

    /**
     * CEK STATUS CHECK-IN HARI INI
     */
    public function isCheckedIn(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $attendance = Attendance::where('user_id', $request->user()->id)
            ->where('date', $today)
            ->first();

        return response()->json([
            'checked_in' => $attendance ? true : false,
            'data'       => $attendance
        ]);
    }
}
