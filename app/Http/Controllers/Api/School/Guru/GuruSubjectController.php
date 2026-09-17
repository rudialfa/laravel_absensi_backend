<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;

class GuruSubjectController extends Controller
{
    /**
     * GET /api/school/guru/subjects
     * Read-only — dipakai buat dropdown pilih mapel di Tugas/Nilai/Jadwal.
     */
    public function index(Request $request)
    {
        $subjects = Subject::where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data mata pelajaran berhasil diambil',
            'data'    => $subjects,
        ]);
    }
}
