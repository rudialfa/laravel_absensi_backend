<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\User;

class PesantrenUstadzAttendanceController extends Controller
{


    // kode 2
    // ==========================
    // Helper: Pastikan role ustadz
    // ==========================
    private function ensureUstadz()
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus ustadz)'
            ], 403));
        }
    }

    // ==========================
    // ABSEN USTADZ: CHECK-IN
    // ==========================
    public function checkIn(Request $request)
    {
        $this->ensureUstadz();

        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        // Cegah double check-in untuk ustadz
        $exists = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by') // penting: ini attendance SELF
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
    // ABSEN USTADZ: CHECK-OUT
    // ==========================
    public function checkOut(Request $request)
    {
        $this->ensureUstadz();

        $request->validate([
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by') // SELF
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
    // ABSEN USTADZ: STATUS HARI INI
    // ==========================
    public function isCheckedIn()
    {
        $this->ensureUstadz();

        $today = Carbon::today();

        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', $today)
            ->whereNull('marked_by') // SELF
            ->first();

        return response()->json([
            'status' => true,
            'checked_in' => $attendance ? true : false,
            'checked_out' => $attendance && $attendance->time_out ? true : false,
            'attendance' => $attendance
        ], 200);
    }

    // =====================
    // LIST SANTRI TODAY
    // =====================

    // kode revisi 2
    public function santriToday()
    {
        $this->ensureUstadz();

        $today = Carbon::today();

        $santri = User::query()
            ->where('role', 'santri')
            // Kalau kamu punya multi-pesantren, aktifkan filter ini sesuai kolom kamu:
            // ->where('pesantren_id', auth()->user()->pesantren_id)
            ->select(['id', 'name', 'face_embedding']) // <-- penting untuk face match
            ->with(['attendances' => function ($q) use ($today) {
                $q->whereDate('date', $today)
                    ->orderByDesc('marked_by')   // marked_by != null dianggap "lebih tinggi"
                    ->orderByDesc('id');         // fallback: yang terakhir
            }])
            ->get()
            ->map(function ($s) {
                $attendance = $s->attendances->first(); // karena sudah di-order di query

                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'face_embedding' => $s->face_embedding, // <-- ini yang dibutuhkan Flutter
                    'status' => $attendance->status ?? 'absent',
                    'marked_by' => $attendance->marked_by ?? null,
                    'time_in' => $attendance->time_in ?? null,
                ];
            });

        return response()->json([
            'status' => true,
            'date' => $today->toDateString(),
            'data' => $santri,
        ], 200);
    }

    // =====================
    // MARK SANTRI ATTENDANCE (klik per santri)
    // =====================
    public function markSantriAttendance(Request $request)
    {
        $this->ensureUstadz();

        $request->validate([
            'santri_id' => 'required|exists:users,id',
            'status' => 'required|in:on_time,permission,overtime,absent'
        ]);

        // Pastikan yang diabsenkan benar2 santri
        $santri = User::where('id', $request->santri_id)
            ->where('role', 'santri')
            ->first();

        if (!$santri) {
            return response()->json([
                'status' => false,
                'message' => 'User bukan santri'
            ], 422);
        }

        $today = Carbon::today();

        // Kalau kamu sudah buat time_in nullable:
        $timeIn = $request->status === 'absent'
            ? null
            : Carbon::now()->format('H:i:s');

        $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $request->santri_id,
                'date' => $today
            ],
            [
                'time_in' => $timeIn,
                'status' => $request->status,
                'latlon_in' => '-',          // karena ini input klik
                'marked_by' => auth()->id(), // ini penting
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Absensi santri diperbarui',
            'data' => [
                'santri_id' => (int) $request->santri_id,
                'status' => $request->status,
                'date' => $today->toDateString(),
                'marked_by' => auth()->id(),
                'attendance_id' => $attendance->id
            ]
        ], 200);
    }

    // =====================
    // SANTRI HISTORY (30 record terakhir)
    // =====================
    public function santriHistory($id)
    {
        $this->ensureUstadz();

        $santri = User::where('id', $id)
            ->where('role', 'santri')
            ->first();

        if (!$santri) {
            return response()->json([
                'status' => false,
                'message' => 'User bukan santri / tidak ditemukan'
            ], 404);
        }

        $history = Attendance::where('user_id', $id)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        return response()->json([
            'status' => true,
            'santri' => [
                'id' => $santri->id,
                'name' => $santri->name,
            ],
            'data' => $history
        ], 200);
    }
}
