<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\MutabaahRecord;
use Illuminate\Http\Request;

class AdminMutabaahController extends Controller
{
    /**
     * GET /api/school/admin/mutabaah-report?class_id=&from=&to=
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'class_id' => 'nullable|exists:class_rooms,id',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
        ]);

        $records = MutabaahRecord::where('company_id', $request->user()->company_id)
            ->with(['student:id,name,class_id', 'student.classRoom:id,name', 'recordedBy:id,name'])
            ->when($data['class_id'] ?? null, fn($q, $id) => $q->whereHas('student', fn($q2) => $q2->where('class_id', $id)))
            ->when($data['from'] ?? null, fn($q, $from) => $q->whereDate('tanggal', '>=', $from))
            ->when($data['to'] ?? null, fn($q, $to) => $q->whereDate('tanggal', '<=', $to))
            ->latest('tanggal')
            ->paginate(30);

        return response()->json([
            'status'  => true,
            'message' => 'Rekap mutabaah berhasil diambil',
            'data'    => $records,
        ]);
    }
}
