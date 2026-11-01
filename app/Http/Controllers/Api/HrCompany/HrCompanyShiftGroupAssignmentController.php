<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Models\ShiftGroupAssignment;
use App\Services\Notifiers\ShiftNotifier;
use Illuminate\Http\Request;

class HrCompanyShiftGroupAssignmentController extends Controller
{
    public function __construct(private ShiftNotifier $shiftNotifier) {}

    // Sebelumnya tidak ada cek role
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['success' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function getGroupOrFail(int $id): ShiftGroup
    {
        return ShiftGroup::where('company_id', $this->companyId())->findOrFail($id);
    }

    private function hasOverlap(int $groupId, string $start, ?string $end, ?int $exceptId = null): bool
    {
        // existing [s1, e1], new [s2, e2] → overlap jika s1 <= e2 && (e1 null || e1 >= s2)
        return ShiftGroupAssignment::where('company_id', $this->companyId())
            ->where('shift_group_id', $groupId)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->where(function ($q) use ($start, $end) {
                if ($end) {
                    $q->where('start_date', '<=', $end);
                }
                $q->where(function ($q2) use ($start) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', $start);
                });
            })
            ->exists();
    }

    // ============================================================
    // GET /api/company/hr/shift-groups/{id}/assignments
    // ============================================================
    public function index(Request $request, $groupId)
    {
        $this->ensureHr();

        $group = $this->getGroupOrFail((int) $groupId);

        $data = ShiftGroupAssignment::query()
            ->where('company_id', $this->companyId())
            ->where('shift_group_id', $group->id)
            ->with(['shift:id,name,start_time,end_time'])
            ->orderBy('start_date', 'desc')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'List assignment shift group',
            'data'    => $data,
        ]);
    }

    // ============================================================
    // POST /api/company/hr/shift-groups/{id}/assignments
    // body: shift_id, start_date, end_date, note, is_rotation, rotation_cycle_days,
    //       notify (opsional, default true)
    // → notifikasi ke semua anggota grup
    // ============================================================
    public function store(Request $request, $groupId)
    {
        $this->ensureHr();

        $companyId = $this->companyId();
        $group     = $this->getGroupOrFail((int) $groupId);

        $validated = $request->validate([
            'shift_id'            => ['required', 'integer'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'note'                => ['nullable', 'string', 'max:500'],
            'is_rotation'         => ['nullable', 'boolean'],
            'rotation_cycle_days' => ['nullable', 'integer', 'min:1'],
            'notify'              => ['nullable', 'boolean'],
        ]);

        $shift = Shift::where('company_id', $companyId)->findOrFail($validated['shift_id']);

        if ($this->hasOverlap($group->id, $validated['start_date'], $validated['end_date'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Range tanggal bentrok dengan assignment lain untuk group ini.',
            ], 422);
        }

        $assignment = ShiftGroupAssignment::create([
            'company_id'          => $companyId,
            'shift_group_id'      => $group->id,
            'shift_id'            => $shift->id,
            'start_date'          => $validated['start_date'],
            'end_date'            => $validated['end_date'] ?? null,
            'created_by'          => auth()->id(),
            'note'                => $validated['note'] ?? null,
            'is_rotation'         => (bool) ($validated['is_rotation'] ?? false),
            'rotation_cycle_days' => $validated['rotation_cycle_days'] ?? null,
        ]);

        // 🔔 Notifikasi ke anggota grup
        if ($request->boolean('notify', true)) {
            $this->shiftNotifier->groupAssigned($assignment, false);
        }

        $assignment->load(['shift:id,name,start_time,end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Assignment shift group berhasil dibuat.',
            'data'    => $assignment,
        ], 201);
    }

    // ============================================================
    // PUT /api/company/hr/shift-groups/shift-group-assignments/{id}
    // → notifikasi ke anggota grup kalau shift / tanggal berubah
    // ============================================================
    public function update(Request $request, $id)
    {
        $this->ensureHr();

        $companyId  = $this->companyId();
        $assignment = ShiftGroupAssignment::where('company_id', $companyId)->findOrFail((int) $id);

        $validated = $request->validate([
            'shift_id'            => ['required', 'integer'],
            'start_date'          => ['required', 'date'],
            'end_date'            => ['nullable', 'date', 'after_or_equal:start_date'],
            'note'                => ['nullable', 'string', 'max:500'],
            'is_rotation'         => ['nullable', 'boolean'],
            'rotation_cycle_days' => ['nullable', 'integer', 'min:1'],
            'notify'              => ['nullable', 'boolean'],
        ]);

        $shift = Shift::where('company_id', $companyId)->findOrFail($validated['shift_id']);

        if ($this->hasOverlap($assignment->shift_group_id, $validated['start_date'], $validated['end_date'] ?? null, $assignment->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Range tanggal bentrok dengan assignment lain untuk group ini.',
            ], 422);
        }

        $before = [
            'shift_id'   => (int) $assignment->shift_id,
            'start_date' => \Carbon\Carbon::parse($assignment->start_date)->toDateString(),
            'end_date'   => $assignment->end_date ? \Carbon\Carbon::parse($assignment->end_date)->toDateString() : null,
        ];

        $assignment->update([
            'shift_id'            => $shift->id,
            'start_date'          => $validated['start_date'],
            'end_date'            => $validated['end_date'] ?? null,
            'note'                => $validated['note'] ?? null,
            'is_rotation'         => (bool) ($validated['is_rotation'] ?? $assignment->is_rotation),
            'rotation_cycle_days' => $validated['rotation_cycle_days'] ?? $assignment->rotation_cycle_days,
        ]);

        $after = [
            'shift_id'   => (int) $shift->id,
            'start_date' => \Carbon\Carbon::parse($validated['start_date'])->toDateString(),
            'end_date'   => !empty($validated['end_date']) ? \Carbon\Carbon::parse($validated['end_date'])->toDateString() : null,
        ];

        // 🔔 Notifikasi hanya kalau shift / tanggal berubah (bukan cuma catatan)
        if ($before !== $after && $request->boolean('notify', true)) {
            $this->shiftNotifier->groupAssigned($assignment->fresh(), true);
        }

        $assignment->load(['shift:id,name,start_time,end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Assignment shift group berhasil diupdate.',
            'data'    => $assignment,
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/shift-groups/shift-group-assignments/{id}
    // ============================================================
    public function destroy($id)
    {
        $this->ensureHr();

        $assignment = ShiftGroupAssignment::where('company_id', $this->companyId())->findOrFail((int) $id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment shift group berhasil dihapus.',
        ]);
    }
}
