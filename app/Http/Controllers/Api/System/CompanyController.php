<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        return response()->json(Company::orderBy('name')->get());
    }

    public function show(Company $company)
    {
        return response()->json($company);
    }

    public function deactivate(Request $request, Company $company)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if (!$company->is_active) {
            return response()->json(['message' => 'Sekolah ini sudah nonaktif.'], 422);
        }

        $company->update([
            'is_active'      => false,
            'suspended_at'   => now(),
            'suspend_reason' => $data['reason'],
        ]);

        DB::table('personal_access_tokens')
            ->whereIn('tokenable_id', $company->users()->pluck('id'))
            ->where('tokenable_type', \App\Models\User::class)
            ->delete();

        return response()->json([
            'message' => 'Sekolah berhasil dinonaktifkan. Semua sesi login user di sekolah ini sudah dilogout paksa.',
            'company' => $company,
        ]);
    }

    public function reactivate(Company $company)
    {
        if ($company->is_active) {
            return response()->json(['message' => 'Sekolah ini sudah aktif.'], 422);
        }

        $company->update([
            'is_active'      => true,
            'suspended_at'   => null,
            'suspend_reason' => null,
        ]);

        return response()->json([
            'message' => 'Sekolah berhasil diaktifkan kembali. Data tetap utuh, user bisa login lagi seperti biasa.',
            'company' => $company,
        ]);
    }
}
