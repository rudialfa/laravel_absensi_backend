<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahfidzSetoran;
use Illuminate\Http\Request;

class AdminTahfidzController extends Controller
{
    /**
     * GET /api/school/admin/tahfidz-report?class_id=&from=&to=
     * Rekap perkembangan tahfidz seluruh sekolah — filter opsional.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'class_id' => 'nullable|exists:class_rooms,id',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
        ]);

        $setorans = TahfidzSetoran::where('company_id', $request->user()->company_id)
            ->with(['student:id,name,class_id', 'student.classRoom:id,name', 'guru:id,name'])
            ->when($data['class_id'] ?? null, fn($q, $id) => $q->whereHas('student', fn($q2) => $q2->where('class_id', $id)))
            ->when($data['from'] ?? null, fn($q, $from) => $q->whereDate('tanggal', '>=', $from))
            ->when($data['to'] ?? null, fn($q, $to) => $q->whereDate('tanggal', '<=', $to))
            ->latest('tanggal')
            ->paginate(30);

        return response()->json([
            'status'  => true,
            'message' => 'Rekap tahfidz berhasil diambil',
            'data'    => $setorans,
        ]);
    }
}
