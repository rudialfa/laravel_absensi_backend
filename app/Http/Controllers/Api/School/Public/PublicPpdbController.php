<?php

namespace App\Http\Controllers\Api\School\Public;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PpdbApplicant;
use App\Models\PpdbPeriod;
use Illuminate\Http\Request;

class PublicPpdbController extends Controller
{
    /**
     * GET /api/public/ppdb/active-period/{companyId}
     * Dipanggil dari halaman publik/landing pendaftaran — cek apakah
     * sekolah ini sedang buka pendaftaran, sebelum tampilkan form.
     */
    public function activePeriod($companyId)
    {
        $company = Company::where('id', $companyId)->where('type', 'school')->first();
        abort_unless($company, 404, 'Sekolah tidak ditemukan');

        $period = PpdbPeriod::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        $isOpen = $period && $period->isOpen();

        return response()->json([
            'status'  => true,
            'message' => $isOpen ? 'Pendaftaran sedang dibuka' : 'Pendaftaran tidak tersedia saat ini',
            'data'    => [
                'is_open'         => $isOpen,
                'period'          => $period,
                'company_name'    => $company->name,
                'is_boarding'     => $company->is_boarding,
            ],
        ]);
    }

    /**
     * POST /api/public/ppdb/register
     * Form pendaftaran publik — siapa saja bisa akses tanpa login.
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'company_id'       => 'required|exists:companies,id',
            'full_name'        => 'required|string|max:150',
            'gender'           => 'required|in:L,P',
            'birth_date'       => 'required|date',
            'previous_school'  => 'nullable|string|max:150',
            'parent_name'      => 'required|string|max:150',
            'parent_phone'     => 'required|string|max:30',
            'parent_email'     => 'nullable|email',
            'wants_boarding'   => 'boolean',
        ]);

        $period = PpdbPeriod::where('company_id', $data['company_id'])
            ->where('is_active', true)
            ->first();

        abort_unless($period && $period->isOpen(), 422, 'Pendaftaran sedang tidak dibuka');

        if ($period->quota) {
            $registeredCount = PpdbApplicant::where('ppdb_period_id', $period->id)->count();
            abort_if($registeredCount >= $period->quota, 422, 'Kuota pendaftaran sudah penuh');
        }

        $data['ppdb_period_id'] = $period->id;
        $data['status'] = 'pending';

        $applicant = PpdbApplicant::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Pendaftaran berhasil dikirim. Silakan tunggu informasi selanjutnya dari sekolah.',
            'data'    => $applicant,
        ], 201);
    }

    public function searchSchools(Request $request)
    {
        $search = $request->query('search');

        $schools = Company::where('type', 'school')
            ->where('is_active', true)
            ->when($search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'address', 'is_boarding']);

        return response()->json([
            'status'  => true,
            'message' => 'Data sekolah berhasil diambil',
            'data'    => $schools,
        ]);
    }
}
