<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use Illuminate\Http\Request;

class GuruClassController extends Controller
{
    /**
     * GET /api/school/guru/my-classes
     * Semua kelas yang diampu guru ini (wali kelas maupun guru mapel).
     */
    public function index(Request $request)
    {
        $classes = $request->user()->teachingClasses()
            ->withCount('students')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data kelas berhasil diambil',
            'data'    => $classes,
        ]);
    }

    /**
     * GET /api/school/guru/my-classes/{class}/students
     * Daftar murid aktif di 1 kelas (roster).
     */
    public function students(ClassRoom $class)
    {
        $this->authorize('view', $class);

        $students = $class->students()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data murid berhasil diambil',
            'data'    => $students,
        ]);
    }
}
