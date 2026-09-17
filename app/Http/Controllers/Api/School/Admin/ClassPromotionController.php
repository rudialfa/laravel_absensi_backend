<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassPromotion;
use App\Models\ClassRoom;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassPromotionController extends Controller
{
    /**
     * GET /api/school/admin/class-promotions/preview?class_id=
     * Daftar murid di 1 kelas, buat direview admin sebelum diproses.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'class_id' => 'required|exists:class_rooms,id',
        ]);

        $class = ClassRoom::findOrFail($data['class_id']);

        abort_if($class->company_id !== $request->user()->company_id, 403);

        $students = Student::where('class_id', $class->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'nis']);

        return response()->json([
            'status'  => true,
            'message' => 'Daftar murid berhasil diambil',
            'data'    => [
                'class' => $class,
                'students' => $students,
            ],
        ]);
    }

    /**
     * POST /api/school/admin/class-promotions/process
     * Proses kenaikan kelas untuk banyak murid sekaligus dalam 1 transaksi.
     *
     * Body:
     * {
     *   "from_class_id": 1,
     *   "to_academic_year": "2027/2028",
     *   "promotions": [
     *     {"student_id": 1, "status": "naik", "to_class_id": 5},
     *     {"student_id": 2, "status": "tinggal"},
     *     {"student_id": 3, "status": "lulus"}
     *   ]
     * }
     */
    public function process(Request $request)
    {
        $data = $request->validate([
            'from_class_id'    => 'required|exists:class_rooms,id',
            'to_academic_year' => 'required|string',
            'promotions'                    => 'required|array|min:1',
            'promotions.*.student_id'       => 'required|exists:students,id',
            'promotions.*.status'           => 'required|in:naik,tinggal,lulus',
            'promotions.*.to_class_id'      => 'required_if:promotions.*.status,naik|nullable|exists:class_rooms,id',
        ]);

        $fromClass = ClassRoom::findOrFail($data['from_class_id']);
        abort_if($fromClass->company_id !== $request->user()->company_id, 403);

        $results = DB::transaction(function () use ($data, $fromClass, $request) {
            $saved = [];

            foreach ($data['promotions'] as $item) {
                $student = Student::findOrFail($item['student_id']);

                abort_if($student->class_id !== $fromClass->id, 422, "Murid {$student->name} bukan bagian dari kelas asal");

                $toClassId = $item['status'] === 'naik' ? $item['to_class_id'] : null;

                $promotion = ClassPromotion::create([
                    'company_id'         => $fromClass->company_id,
                    'student_id'         => $student->id,
                    'from_class_id'      => $fromClass->id,
                    'to_class_id'        => $toClassId,
                    'status'             => $item['status'],
                    'from_academic_year' => $fromClass->academic_year,
                    'to_academic_year'   => $data['to_academic_year'],
                    'processed_by'       => $request->user()->id,
                ]);

                // Update kelas murid sesuai hasil kenaikan.
                // 'tinggal' -> class_id tetap sama (ulang di kelas yang sama).
                // 'lulus'   -> class_id null + is_active false (keluar dari sistem aktif).
                // 'naik'    -> pindah ke to_class_id.
                if ($item['status'] === 'naik') {
                    $student->update(['class_id' => $toClassId]);
                } elseif ($item['status'] === 'lulus') {
                    $student->update(['class_id' => null, 'is_active' => false]);
                }
                // 'tinggal' -> tidak ada perubahan pada students, murid tetap di kelas yang sama

                $saved[] = $promotion->load(['student:id,name', 'toClass:id,name']);
            }

            return $saved;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Kenaikan kelas berhasil diproses untuk ' . count($results) . ' murid',
            'data'    => $results,
        ], 201);
    }

    /**
     * GET /api/school/admin/class-promotions/history?academic_year=
     */
    public function history(Request $request)
    {
        $promotions = ClassPromotion::where('company_id', $request->user()->company_id)
            ->when($request->query('academic_year'), fn($q, $y) => $q->where('to_academic_year', $y))
            ->with(['student:id,name', 'fromClass:id,name', 'toClass:id,name'])
            ->latest()
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat kenaikan kelas berhasil diambil',
            'data'    => $promotions,
        ]);
    }
}
