<?php

namespace App\Http\Controllers\Api\School\Guru;

use App\Http\Controllers\Controller;
use App\Models\MutabaahRecord;
use App\Models\Student;
use Illuminate\Http\Request;

class GuruMutabaahController extends Controller
{
    /**
     * GET /api/school/guru/mutabaah?class_id=&tanggal=
     * List mutabaah untuk 1 kelas pada 1 tanggal (default hari ini).
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'class_id' => 'required|exists:class_rooms,id',
            'tanggal'  => 'nullable|date',
        ]);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $data['class_id'])->exists(),
            403,
            'Kelas ini bukan yang Anda ampu'
        );

        $tanggal = $data['tanggal'] ?? now()->toDateString();

        $students = Student::where('class_id', $data['class_id'])
            ->where('is_active', true)
            ->where('is_boarding', true) // mutabaah cuma relevan untuk santri boarding
            ->orderBy('name')
            ->get()
            ->map(function ($student) use ($tanggal) {
                $record = MutabaahRecord::where('student_id', $student->id)
                    ->where('tanggal', $tanggal)
                    ->first();

                return [
                    'student_id'   => $student->id,
                    'student_name' => $student->name,
                    'record'       => $record,
                ];
            });

        return response()->json([
            'status'  => true,
            'message' => 'Data mutabaah berhasil diambil',
            'data'    => $students,
        ]);
    }

    /**
     * POST /api/school/guru/mutabaah
     * Simpan/update record 1 murid untuk 1 tanggal (updateOrCreate).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id'      => 'required|exists:students,id',
            'tanggal'         => 'required|date',
            'sholat_subuh'    => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_dzuhur'   => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_ashar'    => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_maghrib'  => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_isya'     => 'nullable|in:berjamaah,sendiri,tidak',
            'tilawah_menit'   => 'nullable|integer|min:0',
            'dzikir_pagi'     => 'boolean',
            'dzikir_petang'   => 'boolean',
            'catatan_pembina' => 'nullable|string',
        ]);

        $student = Student::findOrFail($data['student_id']);

        abort_unless(
            $request->user()->teachingClasses()->where('class_rooms.id', $student->class_id)->exists(),
            403,
            'Murid ini bukan bagian dari kelas yang Anda ampu'
        );

        $record = MutabaahRecord::updateOrCreate(
            ['student_id' => $data['student_id'], 'tanggal' => $data['tanggal']],
            [
                'company_id'      => $student->company_id,
                'sholat_subuh'    => $data['sholat_subuh'] ?? null,
                'sholat_dzuhur'   => $data['sholat_dzuhur'] ?? null,
                'sholat_ashar'    => $data['sholat_ashar'] ?? null,
                'sholat_maghrib'  => $data['sholat_maghrib'] ?? null,
                'sholat_isya'     => $data['sholat_isya'] ?? null,
                'tilawah_menit'   => $data['tilawah_menit'] ?? null,
                'dzikir_pagi'     => $data['dzikir_pagi'] ?? false,
                'dzikir_petang'   => $data['dzikir_petang'] ?? false,
                'catatan_pembina' => $data['catatan_pembina'] ?? null,
                'recorded_by'     => $request->user()->id,
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Mutabaah berhasil disimpan',
            'data'    => $record,
        ], 201);
    }

    /**
     * PUT /api/school/guru/mutabaah/{record}
     */
    public function update(Request $request, MutabaahRecord $record)
    {
        $this->authorize('update', $record);

        $data = $request->validate([
            'sholat_subuh'    => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_dzuhur'   => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_ashar'    => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_maghrib'  => 'nullable|in:berjamaah,sendiri,tidak',
            'sholat_isya'     => 'nullable|in:berjamaah,sendiri,tidak',
            'tilawah_menit'   => 'nullable|integer|min:0',
            'dzikir_pagi'     => 'boolean',
            'dzikir_petang'   => 'boolean',
            'catatan_pembina' => 'nullable|string',
        ]);

        $record->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Mutabaah berhasil diperbarui',
            'data'    => $record,
        ]);
    }
}
