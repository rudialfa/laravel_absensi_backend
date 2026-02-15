<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class PesantrenUstadzSantriPermissionController extends Controller
{
    // private function ensureUstadz()
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'ustadz') {
    //         abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus ustadz)'], 403));
    //     }
    // }

    // private function companyId()
    // {
    //     return auth()->user()->company_id ?? null;
    // }

    // public function index(Request $request)
    // {
    //     $this->ensureUstadz();
    //     $q = Permission::with('user')
    //         ->where('company_id', $this->companyId())
    //         ->whereHas('user', fn($u) => $u->where('role', 'santri'));

    //     if ($request->filled('is_approved')) {
    //         $q->where('is_approved', filter_var($request->is_approved, FILTER_VALIDATE_BOOLEAN));
    //     }

    //     return response()->json(['status' => true, 'message' => 'List permission santri', 'data' => $q->orderByDesc('id')->paginate(20)]);
    // }

    // public function show($id)
    // {
    //     $this->ensureUstadz();
    //     $perm = Permission::with('user')
    //         ->where('company_id', $this->companyId())
    //         ->findOrFail($id);

    //     return response()->json(['status' => true, 'message' => 'Detail permission', 'data' => $perm]);
    // }

    // public function approve($id)
    // {
    //     $this->ensureUstadz();
    //     $perm = Permission::where('company_id', $this->companyId())->findOrFail($id);
    //     $perm->is_approved = true;
    //     $perm->save();

    //     return response()->json(['status' => true, 'message' => 'Permission disetujui', 'data' => $perm]);
    // }

    // public function reject($id)
    // {
    //     $this->ensureUstadz();
    //     $perm = Permission::where('company_id', $this->companyId())->findOrFail($id);
    //     $perm->is_approved = false;
    //     $perm->save();

    //     return response()->json(['status' => true, 'message' => 'Permission ditolak', 'data' => $perm]);
    // }

    // kode 2
    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status' => false,
                'message' => 'Akses ditolak (khusus ustadz)',
            ], 403));
        }
    }

    private function companyId(): ?int
    {
        return auth()->user()->company_id ?? null;
    }

    private function baseQuery(Request $request)
    {
        $companyId = $this->companyId();

        $q = Permission::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->whereHas('user', function ($u) {
                $u->where('role', 'santri');
            });

        if ($request->filled('is_approved')) {
            $q->where('is_approved', filter_var($request->is_approved, FILTER_VALIDATE_BOOLEAN));
        }

        return $q;
    }

    public function index(Request $request)
    {
        $this->ensureUstadz();

        $q = $this->baseQuery($request);

        return response()->json([
            'status' => true,
            'message' => 'List permission santri',
            'data' => $q->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show(Request $request, $id)
    {
        $this->ensureUstadz();

        $perm = $this->baseQuery($request)->where('id', $id)->firstOrFail();

        return response()->json([
            'status' => true,
            'message' => 'Detail permission',
            'data' => $perm,
        ]);
    }

    public function approve(Request $request, $id)
    {
        $this->ensureUstadz();

        $perm = $this->baseQuery($request)->where('id', $id)->firstOrFail();

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
        $this->ensureUstadz();

        $perm = $this->baseQuery($request)->where('id', $id)->firstOrFail();

        $perm->is_approved = false;
        $perm->save();

        return response()->json([
            'status' => true,
            'message' => 'Permission ditolak',
            'data' => $perm->fresh('user'),
        ]);
    }
}
