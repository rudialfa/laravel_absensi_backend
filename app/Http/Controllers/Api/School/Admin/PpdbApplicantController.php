<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\PpdbApplicant;
use App\Models\PpdbTestScore;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PpdbApplicantController extends Controller
{
    /**
     * GET /api/school/admin/ppdb-applicants?status=&period_id=
     */
    public function index(Request $request)
    {
        $applicants = PpdbApplicant::where('company_id', $request->user()->company_id)
            ->with('testScores')
            ->when($request->query('status'), fn($q, $s) => $q->where('status', $s))
            ->when($request->query('period_id'), fn($q, $id) => $q->where('ppdb_period_id', $id))
            ->latest()
            ->get();

        return response()->json(['status' => true, 'message' => 'Data pendaftar berhasil diambil', 'data' => $applicants]);
    }

    /**
     * POST /api/school/admin/ppdb-applicants/{applicant}/test-scores
     */
    public function recordTestScore(Request $request, PpdbApplicant $applicant)
    {
        $this->authorize('manage', $applicant);

        $data = $request->validate([
            'jenis'     => 'required|in:akademik,baca_quran,hafalan,wawancara',
            'score'     => 'nullable|numeric|min:0|max:100',
            'notes'     => 'nullable|string',
            'test_date' => 'required|date',
        ]);

        abort_if($applicant->status === 'aktif', 422, 'Pendaftar ini sudah jadi murid');

        $score = PpdbTestScore::updateOrCreate(
            ['ppdb_applicant_id' => $applicant->id, 'jenis' => $data['jenis']],
            [
                'score'      => $data['score'] ?? null,
                'notes'      => $data['notes'] ?? null,
                'test_date'  => $data['test_date'],
                'tested_by'  => $request->user()->id,
            ]
        );

        if ($applicant->status === 'pending') {
            $applicant->update(['status' => 'tes']);
        }

        return response()->json(['status' => true, 'message' => 'Nilai tes berhasil dicatat', 'data' => $score]);
    }

    /**
     * PATCH /api/school/admin/ppdb-applicants/{applicant}/announce
     * Umumkan hasil kelulusan.
     */
    public function announce(Request $request, PpdbApplicant $applicant)
    {
        $this->authorize('manage', $applicant);

        $data = $request->validate([
            'status' => 'required|in:lulus,tidak_lulus',
            'notes'  => 'nullable|string',
        ]);

        // Hasil hanya bisa diumumkan untuk pendaftar yang belum daftar ulang / belum jadi murid
        abort_unless(
            in_array($applicant->status, ['pending', 'tes', 'lulus', 'tidak_lulus'], true),
            422,
            'Pendaftar ini sudah daftar ulang / sudah jadi murid, hasil tidak bisa diubah lagi'
        );

        $applicant->update(['status' => $data['status'], 'notes' => $data['notes'] ?? $applicant->notes]);

        return response()->json([
            'status'  => true,
            'message' => $data['status'] === 'lulus' ? 'Pendaftar dinyatakan lulus' : 'Pendaftar dinyatakan tidak lulus',
            'data'    => $applicant,
        ]);
    }

    /**
     * PATCH /api/school/admin/ppdb-applicants/{applicant}/daftar-ulang
     */
    public function daftarUlang(PpdbApplicant $applicant)
    {
        $this->authorize('manage', $applicant);

        abort_unless($applicant->status === 'lulus', 422, 'Pendaftar harus lulus dulu sebelum daftar ulang');

        $applicant->update(['status' => 'daftar_ulang']);

        return response()->json(['status' => true, 'message' => 'Status daftar ulang tercatat', 'data' => $applicant]);
    }

    /**
     * POST /api/school/admin/ppdb-applicants/{applicant}/convert
     * Konversi jadi siswa aktif — bikin baris baru di tabel students.
     */
    public function convertToStudent(Request $request, PpdbApplicant $applicant)
    {
        $this->authorize('manage', $applicant);

        abort_unless($applicant->status === 'daftar_ulang', 422, 'Pendaftar harus daftar ulang dulu sebelum dikonversi');
        abort_if($applicant->student_id, 422, 'Pendaftar ini sudah dikonversi sebelumnya');

        $data = $request->validate([
            'class_id' => 'required|exists:class_rooms,id',
            'nis'      => 'nullable|string|max:30',
        ]);

        // Kelas tujuan harus milik sekolah yang sama
        abort_unless(
            ClassRoom::where('id', $data['class_id'])->where('company_id', $applicant->company_id)->exists(),
            422,
            'Kelas bukan milik sekolah ini'
        );

        // students.nis wajib diisi & unik per sekolah. Kalau admin tidak mengisi,
        // buat otomatis: tahun + 4 digit urut (contoh 20270001).
        if (!empty($data['nis'])) {
            abort_if(
                Student::withTrashed()->where('company_id', $applicant->company_id)->where('nis', $data['nis'])->exists(),
                422,
                'NIS ' . $data['nis'] . ' sudah dipakai murid lain di sekolah ini'
            );
        }

        $student = DB::transaction(function () use ($applicant, $data) {
            // Kunci pendaftar supaya tidak dikonversi 2x bersamaan
            $applicant = PpdbApplicant::whereKey($applicant->id)->lockForUpdate()->firstOrFail();
            abort_if($applicant->student_id, 422, 'Pendaftar ini sudah dikonversi sebelumnya');

            $student = Student::create([
                'company_id'   => $applicant->company_id,
                'class_id'     => $data['class_id'],
                'name'         => $applicant->full_name,
                'gender'       => $applicant->gender,
                'birth_date'   => $applicant->birth_date,
                'nis'          => $data['nis'] ?? $this->nextNis($applicant->company_id),
                'is_boarding'  => $applicant->wants_boarding,
                'enrolled_at'  => now()->toDateString(),
                'is_active'    => true,
            ]);

            $applicant->update(['status' => 'aktif', 'student_id' => $student->id]);

            return $student;
        });

        return response()->json([
            'status'  => true,
            'message' => 'Pendaftar berhasil dikonversi jadi siswa aktif',
            'data'    => $student,
        ], 201);
    }

    /**
     * NIS otomatis: tahun berjalan + 4 digit urut, lanjut dari NIS terbesar
     * berawalan tahun yang sama di sekolah ini (termasuk murid yang sudah dihapus).
     */
    private function nextNis(int $companyId): string
    {
        $prefix = (string) now()->year;

        $last = Student::withTrashed()
            ->where('company_id', $companyId)
            ->where('nis', 'like', $prefix . '%')
            ->whereRaw('LENGTH(nis) = ?', [strlen($prefix) + 4])
            ->lockForUpdate()
            ->max('nis');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
