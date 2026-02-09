<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\User;

class SantriAttendanceController extends Controller
{
    // ==============================
    // Helper: Pastikan role santri
    // ==============================
    private function ensureSantri()
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus santri)'
            ], 403));
        }
    }

    // =========================================
    // SANTRI: REGISTER / UPDATE FACE EMBEDDING
    // =========================================
    public function registerFace(Request $request)
    {
        $this->ensureSantri();

        $request->validate([
            // format: "0.123,0.456,..." (string panjang)
            'face_embedding' => 'required|string|min:20',
        ]);

        /** @var User $user */
        $user = auth()->user();

        $user->face_embedding = $request->face_embedding;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Face embedding berhasil disimpan',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'face_embedding' => $user->face_embedding,
            ]
        ], 200);
    }

    // ==========================
    // SANTRI: CHECK-IN (SELF)
    // ==========================
    public function checkIn(Request $request)
    {
        $this->ensureSantri();

        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        // Cegah double check-in (SELF = marked_by null)
        $exists = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by')
            ->first();

        if ($exists) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah check-in hari ini'
            ], 422);
        }

        $attendance = new Attendance();
        $attendance->user_id = auth()->id();
        $attendance->marked_by = null; // SELF
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

    // ==========================
    // SANTRI: CHECK-OUT (SELF)
    // ==========================
    public function checkOut(Request $request)
    {
        $this->ensureSantri();

        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by')
            ->first();

        if (!$attendance) {
            return response()->json([
                'status' => false,
                'message' => 'Belum check-in'
            ], 422);
        }

        if ($attendance->time_out) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah check-out hari ini'
            ], 422);
        }

        $attendance->time_out = Carbon::now()->format('H:i:s');
        $attendance->latlon_out = $request->latitude . ',' . $request->longitude;
        $attendance->save();

        return response()->json([
            'status' => true,
            'message' => 'Check-out berhasil',
            'attendance' => $attendance
        ], 200);
    }

    // ==========================
    // SANTRI: STATUS HARI INI
    // ==========================
    public function isCheckedIn()
    {
        $this->ensureSantri();

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by')
            ->first();

        return response()->json([
            'status' => true,
            'checked_in' => $attendance ? true : false,
            'checked_out' => $attendance && $attendance->time_out ? true : false,
            'attendance' => $attendance
        ], 200);
    }

    // ==========================
    // SANTRI: HISTORY (SELF)
    // ==========================
    public function history(Request $request)
    {
        $this->ensureSantri();

        $limit = (int) ($request->query('limit', 30));
        if ($limit <= 0) $limit = 30;
        if ($limit > 100) $limit = 100;

        $data = Attendance::where('user_id', auth()->id())
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $data
        ], 200);
    }
}
