<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class HrCompanySettingController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function getCompanyOrFail(): Company
    {
        return Company::findOrFail($this->companyId());
    }

    // =========================================================
    // GET /api/company/hr/settings/company
    //
    // Ambil detail data perusahaan
    // =========================================================
    public function show()
    {
        $company = $this->getCompanyOrFail();

        return response()->json([
            'success' => true,
            'message' => 'Data perusahaan',
            'data'    => $company,
        ]);
    }

    // =========================================================
    // PUT /api/company/hr/settings/company
    //
    // Update data perusahaan:
    //   name, email, address, latitude, longitude,
    //   radius_km, time_in, time_out, timezone, type
    // =========================================================
    public function update(Request $request)
    {
        $company = $this->getCompanyOrFail();

        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'email', 'max:255'],
            'address'   => ['sometimes', 'required', 'string', 'max:500'],
            'latitude'  => ['sometimes', 'required', 'numeric'],
            'longitude' => ['sometimes', 'required', 'numeric'],
            'radius_km' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'time_in'   => ['sometimes', 'required', 'date_format:H:i'],
            'time_out'  => ['sometimes', 'required', 'date_format:H:i'],
            'timezone'  => ['sometimes', 'required', 'string', 'timezone'],
            'type'      => ['sometimes', 'required', 'in:company,school,pesantren'],
        ]);

        $company->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data perusahaan berhasil diperbarui.',
            'data'    => $company->fresh(),
        ]);
    }

    // =========================================================
    // POST /api/company/hr/settings/company/logo
    //
    // Upload/ganti logo perusahaan
    // Body: multipart/form-data  image = file (jpg/jpeg/png/webp, max 2MB)
    // Disimpan di: public/image/logo/
    // Diakses via: asset('image/logo/filename.ext')
    // =========================================================
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $company = $this->getCompanyOrFail();

        // ── Hapus logo lama jika ada ──
        if ($company->image_url) {
            // image_url disimpan sebagai path relatif: 'image/logo/xxx.jpg'
            $oldFullPath = public_path($company->image_url);
            if (File::exists($oldFullPath)) {
                File::delete($oldFullPath);
            }
        }

        // ── Simpan file baru ke public/image/logo/ ──
        $destinationPath = public_path('image/logo');

        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        $file     = $request->file('image');
        $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        $file->move($destinationPath, $fileName);

        // Simpan path relatif (bukan full URL) agar portable
        $imagePath = 'image/logo/' . $fileName;

        $company->update(['image_url' => $imagePath]);

        return response()->json([
            'success'    => true,
            'message'    => 'Logo perusahaan berhasil diupload.',
            'image_url'  => $imagePath,
            'image_full_url' => asset($imagePath),
        ]);
    }

    // =========================================================
    // GET /api/company/hr/settings/company/employees
    // Query: ?search=&department=&per_page=15
    //
    // List semua karyawan perusahaan (untuk halaman pengaturan)
    // =========================================================
    public function employees(Request $request)
    {
        $companyId = $this->companyId();

        $q = \App\Models\User::where('company_id', $companyId)
            ->where('role', '!=', 'company')
            ->select('id', 'name', 'email', 'phone', 'position', 'department', 'image_url', 'role');

        if ($search = $request->get('search')) {
            $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('position', 'like', "%$search%");
            });
        }

        if ($dept = $request->get('department')) {
            $q->where('department', $dept);
        }

        $data = $q->orderBy('name')->paginate((int) ($request->get('per_page', 15)));

        return response()->json([
            'success' => true,
            'message' => 'Daftar karyawan',
            'data'    => $data,
        ]);
    }

    // =========================================================
    // GET /api/company/hr/settings/company/departments
    //
    // List semua departemen unik di perusahaan
    // =========================================================
    public function departments()
    {
        $companyId = $this->companyId();

        $departments = \App\Models\User::where('company_id', $companyId)
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Daftar departemen',
            'data'    => $departments,
        ]);
    }
}
