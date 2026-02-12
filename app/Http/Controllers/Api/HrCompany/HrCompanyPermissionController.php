<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class HrCompanyPermissionController extends Controller
{
    private function ensureHr()
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index(Request $request)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $q = Permission::with('user')->where('company_id', $companyId);

        if ($request->filled('is_approved')) {
            $q->where('is_approved', filter_var($request->is_approved, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'status' => true,
            'message' => 'List permissions',
            'data' => $q->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $perm = Permission::with('user')->where('company_id', $companyId)->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail permission', 'data' => $perm]);
    }

    public function approve($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $perm = Permission::where('company_id', $companyId)->findOrFail($id);
        $perm->is_approved = true;
        $perm->save();

        return response()->json(['status' => true, 'message' => 'Permission disetujui', 'data' => $perm]);
    }

    public function reject($id)
    {
        $this->ensureHr();
        $companyId = $this->companyId();

        $perm = Permission::where('company_id', $companyId)->findOrFail($id);
        $perm->is_approved = false; // reject = tetap false (kalau mau alasan reject, nanti tambah kolom)
        $perm->save();

        return response()->json(['status' => true, 'message' => 'Permission ditolak', 'data' => $perm]);
    }
}
