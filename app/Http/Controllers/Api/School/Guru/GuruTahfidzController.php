<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\TahfidzSetoran;
use Illuminate\Http\Request;

class GuruTahfidzController extends Controller
{
    /**
     * GET /api/school/guru/tahfidz?class_id=&student_id=
     * List setoran untuk murid di kelas yang diampu guru ini.
     */
    public function index(Request $request)
    {
        $classIds = $request->user()->teachingClasses()->pluck('class_rooms.id');

        $setorans = TahfidzSetoran::whereHas(
            'student',
            fn($q) => $q->whereIn('class_id', $classIds)
        )
            ->with(['student:id,name,class_id', 'guru:id,name'])
            ->when($request->query('student_id'), fn($q, $id) => $q->where('student_id', $id))
            ->latest('tanggal')
            ->paginate(20);

        return response()->json([
            'status'  => true,
            'message' => 'Data setoran tahfidz berhasil diambil',
            'data'    => $setorans,
        ]);
    }

    /**
     * POST /api/school/guru/tahfidz
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'juz'        => 'required|integer|min:1|max:30',
            'surah'      => 'required|string|max:100',
            'ayat_awal'  => 'required|integer|min:1',
            'ayat_akhir' => 'required|integer|gte:ayat_awal',
            'jenis'      => 'required|in:ziyadah,murajaah',
            'status'     => 'required|in:lancar,kurang_lancar,mengulang',
            'catatan'    => 'nullable|string',
            'tanggal'    => 'required|date',
        ]);

        $student = \App\Models\Student::findOrFail($data['student_id']);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $student->class_id)->exists(),
            403,
            'Murid ini bukan bagian dari kelas yang Anda ampu'
        );

        $data['company_id'] = $request->user()->company_id;
        $data['guru_id'] = $request->user()->id;

        $setoran = TahfidzSetoran::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Setoran berhasil dicatat',
            'data'    => $setoran->load(['student:id,name', 'guru:id,name']),
        ], 201);
    }

    /**
     * PUT /api/school/guru/tahfidz/{setoran}
     */
    public function update(Request $request, TahfidzSetoran $setoran)
    {
        $this->authorize('update', $setoran);

        $data = $request->validate([
            'juz'        => 'sometimes|integer|min:1|max:30',
            'surah'      => 'sometimes|string|max:100',
            'ayat_awal'  => 'sometimes|integer|min:1',
            'ayat_akhir' => 'sometimes|integer|gte:ayat_awal',
            'jenis'      => 'sometimes|in:ziyadah,murajaah',
            'status'     => 'sometimes|in:lancar,kurang_lancar,mengulang',
            'catatan'    => 'nullable|string',
        ]);

        $setoran->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Setoran berhasil diperbarui',
            'data'    => $setoran->load(['student:id,name', 'guru:id,name']),
        ]);
    }

    /**
     * DELETE /api/school/guru/tahfidz/{setoran}
     */
    public function destroy(TahfidzSetoran $setoran)
    {
        $this->authorize('delete', $setoran);

        $setoran->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Setoran dihapus',
            'data'    => null,
        ]);
    }
}
