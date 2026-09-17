<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use Illuminate\Http\Request;

class GuruCurriculumController extends Controller
{
    /**
     * GET /api/school/guru/curriculums/active
     * Kurikulum yang sedang aktif — dipakai buat referensi Jadwal Pelajaran.
     */
    public function active(Request $request)
    {
        $curriculum = Curriculum::where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->with('curriculumSubjects.subject')
            ->first();

        return response()->json([
            'status'  => true,
            'message' => $curriculum ? 'Kurikulum aktif berhasil diambil' : 'Belum ada kurikulum aktif',
            'data'    => $curriculum,
        ]);
    }
}
