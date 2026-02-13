<?php

namespace App\Http\Controllers\Api\Santri;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class SantriPermissionController extends Controller
{
    private function ensureSantri()
    {
        if (!auth()->check() || auth()->user()->role !== 'santri') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus santri)'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function index()
    {
        $this->ensureSantri();

        $data = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['status' => true, 'message' => 'List permission santri', 'data' => $data]);
    }

    public function store(Request $request)
    {
        $this->ensureSantri();

        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'date' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $perm = Permission::create([
            'company_id' => $this->companyId(),
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'date' => $validated['date'],
            'reason' => $validated['reason'],
            'is_approved' => false,
        ]);

        return response()->json(['status' => true, 'message' => 'Permission dibuat', 'data' => $perm], 201);
    }

    public function show($id)
    {
        $this->ensureSantri();

        $perm = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['status' => true, 'message' => 'Detail permission', 'data' => $perm]);
    }

    public function cancel($id)
    {
        $this->ensureSantri();

        $perm = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if ($perm->is_approved) {
            return response()->json(['status' => false, 'message' => 'Tidak bisa cancel, permission sudah disetujui'], 422);
        }

        $perm->delete();

        return response()->json(['status' => true, 'message' => 'Permission dibatalkan']);
    }
}
