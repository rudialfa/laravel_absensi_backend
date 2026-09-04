<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class WaliAttendanceController extends Controller
{
    /**
     * GET /api/school/wali/my-children/{student}/attendance?from=&to=
     * Riwayat absen 1 anak, bisa difilter range tanggal.
     */
    public function index(Request $request, Student $student)
    {
        $this->authorize('view', $student);

        $data = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $attendances = $student->attendances()
            ->when($data['from'] ?? null, fn($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($data['to'] ?? null, fn($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->paginate(30);

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat absensi berhasil diambil',
            'data'    => $attendances,
        ]);
    }
}
