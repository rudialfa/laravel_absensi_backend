<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\ShiftGroup;
use Illuminate\Http\Request;

class HrCompanyShiftGroupController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();

        $q = ShiftGroup::query()
            ->where('company_id', $companyId)
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $q->where('name', 'like', "%{$search}%");
        }

        $data = $q->paginate((int)($request->get('per_page', 15)));

        return response()->json([
            'success' => true,
            'message' => 'List shift group',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        // unique per company
        $exists = ShiftGroup::where('company_id', $companyId)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Nama shift group sudah digunakan.',
            ], 422);
        }

        $group = ShiftGroup::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift group berhasil dibuat.',
            'data' => $group,
        ], 201);
    }

    public function show($id)
    {
        $companyId = $this->companyId();

        $group = ShiftGroup::where('company_id', $companyId)
            ->withCount('users')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Detail shift group',
            'data' => $group,
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->companyId();

        $group = ShiftGroup::where('company_id', $companyId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $exists = ShiftGroup::where('company_id', $companyId)
            ->where('name', $validated['name'])
            ->where('id', '!=', $group->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Nama shift group sudah digunakan.',
            ], 422);
        }

        $group->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Shift group berhasil diupdate.',
            'data' => $group,
        ]);
    }

    public function destroy($id)
    {
        $companyId = $this->companyId();

        $group = ShiftGroup::where('company_id', $companyId)->findOrFail($id);
        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift group berhasil dihapus.',
        ]);
    }
}
