<?php

namespace App\Http\Controllers\Api\School\Wali;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use Illuminate\Http\Request;

class WaliAcademicEventController extends Controller
{
    /**
     * GET /api/school/wali/academic-events?month=&year=
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'nullable|integer|min:2000',
        ]);

        $events = AcademicEvent::where('company_id', $request->user()->company_id)
            ->with('classRoom:id,name')
            ->when(
                ($data['month'] ?? null) && ($data['year'] ?? null),
                fn($q) => $q->whereYear('tanggal_mulai', $data['year'])
                    ->whereMonth('tanggal_mulai', $data['month'])
            )
            ->orderBy('tanggal_mulai')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data kalender akademik berhasil diambil',
            'data'    => $events,
        ]);
    }
}
