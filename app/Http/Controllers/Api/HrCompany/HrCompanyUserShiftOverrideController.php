<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShiftOverride;
use App\Services\Notifiers\ShiftNotifier;
use Illuminate\Http\Request;

class HrCompanyUserShiftOverrideController extends Controller
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

    private function getUserOrFail(int $userId): User
    {
        return User::where('company_id', $this->companyId())->findOrFail($userId);
    }

    // ============================================================
    // GET /api/company/hr/shift-groups/users/{userId}/shift-overrides
    // ============================================================
    public function index(Request $request, $userId)
    {
        $this->ensureHr();

        $user = $this->getUserOrFail((int) $userId);

        $data = UserShiftOverride::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', $user->id)
            ->with(['shift:id,name,start_time,end_time'])
            ->orderBy('start_date', 'desc')
            ->paginate((int) $request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'List shift override user',
            'data'    => $data,
        ]);
    }

    // ============================================================
    // POST /api/company/hr/shift-groups/users/{userId}/shift-overrides
    // body: shift_id, start_date, end_date (opsional), reason, notify (opsional, default true)
    // → notifikasi ke karyawan
    // ============================================================
    public function store(Request $request, $userId)
    {
        $this->ensureHr();

        $companyId = $this->companyId();
        $user      = $this->getUserOrFail((int) $userId);

        $validated = $request->validate([
            'shift_id'   => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason'     => ['nullable', 'string', 'max:500'],
            'notify'     => ['nullable', 'boolean'],
        ]);

        $shift = Shift::where('company_id', $companyId)->findOrFail($validated['shift_id']);

        // Cegah override aktif yang bentrok
        $start = $validated['start_date'];
        $end   = $validated['end_date'] ?? null;

        $overlap = UserShiftOverride::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(function ($q) use ($start, $end) {
                if ($end) $q->where('start_date', '<=', $end);
                $q->where(function ($q2) use ($start) {
                    $q2->whereNull('end_date')->orWhere('end_date', '>=', $start);
                });
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'success' => false,
                'message' => 'Override bentrok dengan override aktif lain untuk user ini.',
            ], 422);
        }

        $override = UserShiftOverride::create([
            'company_id' => $companyId,
            'user_id'    => $user->id,
            'shift_id'   => $shift->id,
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'] ?? null,
            'status'     => 'active',
            'created_by' => auth()->id(),
            'reason'     => $validated['reason'] ?? null,
        ]);

        // 🔔 Notifikasi ke karyawan
        if ($request->boolean('notify', true)) {
            $this->shiftNotifier->overrideCreated($override);
        }

        $override->load(['shift:id,name,start_time,end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Override shift berhasil dibuat.',
            'data'    => $override,
        ], 201);
    }

    // ============================================================
    // PATCH /api/company/hr/shift-groups/user-shift-overrides/{id}/cancel
    // → notifikasi ke karyawan
    // ============================================================
    public function cancel($id)
    {
        $this->ensureHr();

        $override = UserShiftOverride::where('company_id', $this->companyId())->findOrFail((int) $id);

        $wasActive = $override->status === 'active';

        $override->update(['status' => 'cancelled']);

        // 🔔 Notifikasi ke karyawan (hanya kalau sebelumnya aktif & belum lewat)
        if ($wasActive && (!$override->end_date || now()->startOfDay()->lte($override->end_date))) {
            $this->shiftNotifier->overrideCanceled($override);
        }

        return response()->json([
            'success' => true,
            'message' => 'Override shift berhasil dibatalkan.',
            'data'    => $override,
        ]);
    }

    // ============================================================
    // DELETE /api/company/hr/shift-groups/user-shift-overrides/{id}
    // → notifikasi ke karyawan kalau override masih aktif
    // ============================================================
    public function destroy($id)
    {
        $this->ensureHr();

        $override = UserShiftOverride::where('company_id', $this->companyId())->findOrFail((int) $id);

        $notify = $override->status === 'active'
            && (!$override->end_date || now()->startOfDay()->lte($override->end_date));

        $snapshot = $override->replicate();
        $snapshot->id = $override->id;

        $override->delete();

        if ($notify) {
            $this->shiftNotifier->overrideCanceled($snapshot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Override shift berhasil dihapus.',
        ]);
    }
}
