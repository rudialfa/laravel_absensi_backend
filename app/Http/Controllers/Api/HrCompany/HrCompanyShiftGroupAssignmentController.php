<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Models\ShiftGroupAssignment;
use Illuminate\Http\Request;

class HrCompanyShiftGroupAssignmentController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function getGroupOrFail(int $id): ShiftGroup
    {
        return ShiftGroup::where('company_id', $this->companyId())->findOrFail($id);
    }

    public function index(Request $request, $groupId)
    {
        $companyId = $this->companyId();
        $group = $this->getGroupOrFail((int)$groupId);

        $q = ShiftGroupAssignment::query()
            ->where('company_id', $companyId)
            ->where('shift_group_id', $group->id)
            ->with(['shift:id,name,start_time,end_time'])
            ->orderBy('start_date', 'desc');

        $data = $q->paginate((int)($request->get('per_page', 15)));

        return response()->json([
            'success' => true,
            'message' => 'List assignment shift group',
            'data' => $data,
        ]);
    }

    public function store(Request $request, $groupId)
    {
        $companyId = $this->companyId();
        $group = $this->getGroupOrFail((int)$groupId);

        $validated = $request->validate([
            'shift_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:500'],

            // opsional untuk rotasi (boleh belum dipakai)
            'is_rotation' => ['nullable', 'boolean'],
            'rotation_cycle_days' => ['nullable', 'integer', 'min:1'],
        ]);

        // pastikan shift milik company
        $shift = Shift::where('company_id', $companyId)->findOrFail($validated['shift_id']);

        // OPTIONAL: cegah overlap assignment group yang sama
        // Simple overlap check:
        $start = $validated['start_date'];
        $end = $validated['end_date'] ?? null;

        $overlap = ShiftGroupAssignment::where('company_id', $companyId)
            ->where('shift_group_id', $group->id)
            ->where(function ($q) use ($start, $end) {
                // existing: [s1, e1], new: [s2, e2]
                // overlap jika s1 <= e2 && (e1 null or e1 >= s2)
                if ($end) {
                    $q->where('start_date', '<=', $end);
                }
                // e1 null OR e1 >= s2
                $q->where(function ($q2) use ($start) {
                    $q2->whereNull('end_date')
                        ->orWhere('end_date', '>=', $start);
                });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Range tanggal bentrok dengan assignment lain untuk group ini.',
            ], 422);
        }

        $assignment = ShiftGroupAssignment::create([
            'company_id' => $companyId,
            'shift_group_id' => $group->id,
            'shift_id' => $shift->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'created_by' => auth()->id(),
            'note' => $validated['note'] ?? null,
            'is_rotation' => (bool)($validated['is_rotation'] ?? false),
            'rotation_cycle_days' => $validated['rotation_cycle_days'] ?? null,
        ]);

        $assignment->load(['shift:id,name,start_time,end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Assignment shift group berhasil dibuat.',
            'data' => $assignment,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->companyId();

        $assignment = ShiftGroupAssignment::where('company_id', $companyId)->findOrFail((int)$id);

        $validated = $request->validate([
            'shift_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_rotation' => ['nullable', 'boolean'],
            'rotation_cycle_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $shift = Shift::where('company_id', $companyId)->findOrFail($validated['shift_id']);

        // overlap check (exclude current)
        $start = $validated['start_date'];
        $end = $validated['end_date'] ?? null;

        $overlap = ShiftGroupAssignment::where('company_id', $companyId)
            ->where('shift_group_id', $assignment->shift_group_id)
            ->where('id', '!=', $assignment->id)
            ->where(function ($q) use ($start, $end) {
                if ($end) {
                    $q->where('start_date', '<=', $end);
                }
                $q->where(function ($q2) use ($start) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', $start);
                });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Range tanggal bentrok dengan assignment lain untuk group ini.',
            ], 422);
        }

        $assignment->update([
            'shift_id' => $shift->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'note' => $validated['note'] ?? null,
            'is_rotation' => (bool)($validated['is_rotation'] ?? $assignment->is_rotation),
            'rotation_cycle_days' => $validated['rotation_cycle_days'] ?? $assignment->rotation_cycle_days,
        ]);

        $assignment->load(['shift:id,name,start_time,end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Assignment shift group berhasil diupdate.',
            'data' => $assignment,
        ]);
    }

    public function destroy($id)
    {
        $companyId = $this->companyId();

        $assignment = ShiftGroupAssignment::where('company_id', $companyId)->findOrFail((int)$id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment shift group berhasil dihapus.',
        ]);
    }
}
