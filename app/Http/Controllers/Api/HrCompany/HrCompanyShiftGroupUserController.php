<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\ShiftGroup;
use App\Models\User;
use Illuminate\Http\Request;

class HrCompanyShiftGroupUserController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function getGroupOrFail(int $id): ShiftGroup
    {
        return ShiftGroup::where('company_id', $this->companyId())->findOrFail($id);
    }

    public function index(Request $request, $id)
    {
        $group = $this->getGroupOrFail((int)$id);

        $users = $group->users()
            ->select('users.id', 'users.name', 'users.email', 'users.department', 'users.position')
            ->orderBy('users.name')
            ->paginate((int)($request->get('per_page', 15)));

        return response()->json([
            'success' => true,
            'message' => 'List user dalam shift group',
            'data' => $users,
        ]);
    }

    /**
     * attach:
     * - Bisa kirim user_ids: [1,2,3]
     * - Atau filter: department, position (bulk)
     * Optional: start_date, end_date untuk masa berlaku membership group
     */
    public function attach(Request $request, $id)
    {
        $companyId = $this->companyId();
        $group = $this->getGroupOrFail((int)$id);

        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],

            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],

            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        $userIds = $validated['user_ids'] ?? [];

        // Bulk by filter jika user_ids kosong
        if (empty($userIds)) {
            $q = User::query()->where('company_id', $companyId);

            if (!empty($validated['department'])) {
                $q->where('department', $validated['department']);
            }
            if (!empty($validated['position'])) {
                $q->where('position', $validated['position']);
            }

            $userIds = $q->pluck('id')->toArray();
        }

        if (empty($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada user yang dipilih.',
            ], 422);
        }

        // Validasi semua user harus 1 company
        $countValid = User::where('company_id', $companyId)
            ->whereIn('id', $userIds)
            ->count();

        if ($countValid !== count($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Ada user yang tidak valid / beda company.',
            ], 422);
        }

        // Attach with pivot data
        $pivotData = [];
        foreach ($userIds as $uid) {
            $pivotData[$uid] = [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ];
        }

        // syncWithoutDetaching biar tidak menghapus anggota lama
        $group->users()->syncWithoutDetaching($pivotData);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan ke shift group.',
            'data' => [
                'shift_group_id' => $group->id,
                'added_count' => count($userIds),
            ],
        ]);
    }

    public function detach(Request $request, $id)
    {
        $group = $this->getGroupOrFail((int)$id);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        $group->users()->detach($validated['user_ids']);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dikeluarkan dari shift group.',
            'data' => [
                'shift_group_id' => $group->id,
                'removed_count' => count($validated['user_ids']),
            ],
        ]);
    }
}
