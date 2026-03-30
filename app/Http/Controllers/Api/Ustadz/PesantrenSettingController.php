<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class PesantrenSettingController extends Controller
{
      // ============================================================
    // PRIVATE HELPERS
    // ============================================================
 
    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }
 
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }
 
    private function pesantrenOrFail(): Company
    {
        return Company::findOrFail($this->companyId());
    }
 
    // ============================================================
    // SHOW — GET /api/pesantren/settings/pesantren
    // Sejajar: HrCompanySettingController::show()
    // ============================================================
    public function show()
    {
        $this->ensureUstadz();
 
        $company = $this->pesantrenOrFail();
 
        return response()->json([
            'status'  => true,
            'message' => 'Data pesantren',
            'data'    => $company,
        ]);
    }
 
    // ============================================================
    // UPDATE — PUT /api/pesantren/settings/pesantren
    // Sejajar: HrCompanySettingController::update()
    // ============================================================
    public function update(Request $request)
    {
        $this->ensureUstadz();
 
        $company = $this->pesantrenOrFail();
 
        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'email',  'max:255'],
            'address'   => ['sometimes', 'required', 'string', 'max:500'],
            'city'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude'  => ['sometimes', 'required', 'numeric'],
            'longitude' => ['sometimes', 'required', 'numeric'],
            'radius_km' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'time_in'   => ['sometimes', 'required', 'date_format:H:i'],
            'time_out'  => ['sometimes', 'required', 'date_format:H:i'],
            'timezone'  => ['sometimes', 'required', 'string', 'timezone'],
        ]);
 
        $company->update($validated);
 
        return response()->json([
            'status'  => true,
            'message' => 'Data pesantren berhasil diperbarui.',
            'data'    => $company->fresh(),
        ]);
    }
 
    // ============================================================
    // UPLOAD LOGO — POST /api/pesantren/settings/pesantren/logo
    // Sejajar: HrCompanySettingController::uploadLogo()
    // Body: multipart/form-data  image = file (jpg/jpeg/png/webp, max 2MB)
    // ============================================================
    public function uploadLogo(Request $request)
    {
        $this->ensureUstadz();
 
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
 
        $company = $this->pesantrenOrFail();
 
        // Hapus logo lama jika ada
        if ($company->image_url) {
            $oldFullPath = public_path($company->image_url);
            if (File::exists($oldFullPath)) {
                File::delete($oldFullPath);
            }
        }
 
        // Simpan logo baru ke public/image/logo/
        $destinationPath = public_path('image/logo');
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }
 
        $file      = $request->file('image');
        $fileName  = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($destinationPath, $fileName);
 
        $imagePath = 'image/logo/' . $fileName;
        $company->update(['image_url' => $imagePath]);
 
        return response()->json([
            'status'         => true,
            'message'        => 'Logo pesantren berhasil diupload.',
            'image_url'      => $imagePath,
            'image_full_url' => asset($imagePath),
        ]);
    }
 
    // ============================================================
    // SANTRI LIST — GET /api/pesantren/settings/pesantren/santri
    // Sejajar: HrCompanySettingController::employees()
    // Query: search, department (kelas), position (kamar), per_page
    // ============================================================
    public function santriList(Request $request)
    {
        $this->ensureUstadz();
 
        $q = User::where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->select('id', 'name', 'email', 'phone', 'position', 'department', 'image_url', 'role');
 
        if ($search = $request->get('search')) {
            $q->where(function ($query) use ($search) {
                $query->where('name',     'like', "%$search%")
                      ->orWhere('email',  'like', "%$search%")
                      ->orWhere('phone',  'like', "%$search%");
            });
        }
 
        if ($dept = $request->get('department')) {
            $q->where('department', $dept); // kelas / angkatan
        }
 
        if ($pos = $request->get('position')) {
            $q->where('position', $pos); // kamar
        }
 
        return response()->json([
            'status'  => true,
            'message' => 'Daftar santri',
            'data'    => $q->orderBy('name')
                ->paginate((int) $request->get('per_page', 15)),
        ]);
    }
 
    // ============================================================
    // KAMAR LIST — GET /api/pesantren/settings/pesantren/kamar
    // Sejajar: HrCompanySettingController::departments()
    // List semua kamar unik (position) + kelas unik (department)
    // ============================================================
    public function kamarList()
    {
        $this->ensureUstadz();
 
        $companyId = $this->companyId();
 
        $kamar = User::where('company_id', $companyId)
            ->where('role', 'santri')
            ->whereNotNull('position')
            ->distinct()
            ->pluck('position')
            ->sort()
            ->values();
 
        $kelas = User::where('company_id', $companyId)
            ->where('role', 'santri')
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values();
 
        return response()->json([
            'status'  => true,
            'message' => 'Daftar kamar dan kelas',
            'data'    => [
                'kamar' => $kamar,
                'kelas' => $kelas,
            ],
        ]);
    }
}
