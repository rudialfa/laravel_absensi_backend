<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicEvent;
use Illuminate\Http\Request;

class AcademicEventController extends Controller
{
    /**
     * GET /api/school/admin/academic-events?month=&year=&jenis=
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'nullable|integer|min:2000',
            'jenis' => 'nullable|in:libur,ujian,kegiatan,penting',
        ]);

        $events = AcademicEvent::where('company_id', $request->user()->company_id)
            ->with(['classRoom:id,name', 'createdBy:id,name'])
            ->when($data['jenis'] ?? null, fn($q, $j) => $q->where('jenis', $j))
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

    /**
     * POST /api/school/admin/academic-events
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:150',
            'description'      => 'nullable|string',
            'jenis'            => 'required|in:libur,ujian,kegiatan,penting',
            'tanggal_mulai'    => 'required|date',
            'tanggal_selesai'  => 'required|date|after_or_equal:tanggal_mulai',
            'class_id'         => 'nullable|exists:class_rooms,id',
            'academic_year'    => 'required|string|max:20',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;

        $event = AcademicEvent::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Event kalender berhasil dibuat',
            'data'    => $event->load(['classRoom:id,name', 'createdBy:id,name']),
        ], 201);
    }

    /**
     * GET /api/school/admin/academic-events/{academicEvent}
     */
    public function show(AcademicEvent $academicEvent)
    {
        $this->authorize('view', $academicEvent);

        return response()->json([
            'status'  => true,
            'message' => 'Detail event berhasil diambil',
            'data'    => $academicEvent->load(['classRoom:id,name', 'createdBy:id,name']),
        ]);
    }

    /**
     * PUT /api/school/admin/academic-events/{academicEvent}
     */
    public function update(Request $request, AcademicEvent $academicEvent)
    {
        $this->authorize('update', $academicEvent);

        $data = $request->validate([
            'title'            => 'sometimes|string|max:150',
            'description'      => 'nullable|string',
            'jenis'            => 'sometimes|in:libur,ujian,kegiatan,penting',
            'tanggal_mulai'    => 'sometimes|date',
            'tanggal_selesai'  => 'sometimes|date|after_or_equal:tanggal_mulai',
            'class_id'         => 'nullable|exists:class_rooms,id',
        ]);

        $academicEvent->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Event kalender berhasil diperbarui',
            'data'    => $academicEvent->load(['classRoom:id,name', 'createdBy:id,name']),
        ]);
    }

    /**
     * DELETE /api/school/admin/academic-events/{academicEvent}
     */
    public function destroy(AcademicEvent $academicEvent)
    {
        $this->authorize('delete', $academicEvent);

        $academicEvent->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Event kalender dihapus',
            'data'    => null,
        ]);
    }
}
