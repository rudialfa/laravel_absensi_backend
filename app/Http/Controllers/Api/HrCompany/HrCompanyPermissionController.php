<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class HrCompanyPermissionController extends Controller
{

    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json(['status' => false, 'message' => 'Company ID tidak ditemukan'], 422));
        }
        return (int) $companyId;
    }

    private function baseQuery(Request $request)
    {
        $q = Permission::query()
            ->with('user')
            ->where('company_id', $this->companyId());

        // filter status: pending|approved|rejected
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'pending') $q->whereNull('is_approved');
            if ($status === 'approved') $q->where('is_approved', true);
            if ($status === 'rejected') $q->where('is_approved', false);
        }

        // optional search
        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->whereHas('user', function ($u) use ($keyword) {
                $u->where('name', 'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%");
            });
        }

        return $q;
    }

    public function index(Request $request)
    {
        $this->ensureHr();

        $data = $this->baseQuery($request)
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'List permission company',
            'data' => $data,
        ]);
    }

    public function show(Request $request, $id)
    {
        $this->ensureHr();

        $perm = $this->baseQuery($request)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Detail permission',
            'data' => $perm,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $this->ensureHr();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('id', $id)
            ->firstOrFail();

        $perm->is_approved = true;
        $perm->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission disetujui',
            'data' => $perm->fresh('user'),
        ]);
    }

    public function reject(Request $request, $id)
    {
        $this->ensureHr();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('id', $id)
            ->firstOrFail();

        $perm->is_approved = false;
        $perm->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission ditolak',
            'data' => $perm->fresh('user'),
        ]);
    }
}
