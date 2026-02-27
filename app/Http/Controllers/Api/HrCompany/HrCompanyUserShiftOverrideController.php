<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShiftOverride;
use Illuminate\Http\Request;

class HrCompanyUserShiftOverrideController extends Controller
{
    private function companyId(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function getUserOrFail(int $userId): User
    {
        return User::where('company_id', $this->companyId())->findOrFail($userId);
    }

    public function index(Request $request, $userId)
    {
        $companyId = $this->companyId();
        $user = $this->getUserOrFail((int)$userId);

        $q = UserShiftOverride::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->with(['shift:id,name,start_time,end_time'])
            ->orderBy('start_date', 'desc');

        $data = $q->paginate((int)($request->get('per_page', 15)));

        return response()->json([
            'success' => true,
            'message' => 'List shift override user',
            'data' => $data,
        ]);
    }

    public function store(Request $request, $userId)
    {
        $companyId = $this->companyId();
        $user = $this->getUserOrFail((int)$userId);

        $validated = $request->validate([
            'shift_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $shift = Shift::where('company_id', $companyId)->findOrFail($validated['shift_id']);

        // OPTIONAL: cegah override overlap aktif
        $start = $validated['start_date'];
        $end = $validated['end_date'] ?? null;

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
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'status' => 'active',
            'created_by' => auth()->id(),
            'reason' => $validated['reason'] ?? null,
        ]);

        $override->load(['shift:id,name,start_time,end_time']);

        return response()->json([
            'success' => true,
            'message' => 'Override shift berhasil dibuat.',
            'data' => $override,
        ], 201);
    }

    public function cancel($id)
    {
        $companyId = $this->companyId();

        $override = UserShiftOverride::where('company_id', $companyId)->findOrFail((int)$id);
        $override->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Override shift berhasil dibatalkan.',
            'data' => $override,
        ]);
    }

    public function destroy($id)
    {
        $companyId = $this->companyId();

        $override = UserShiftOverride::where('company_id', $companyId)->findOrFail((int)$id);
        $override->delete();

        return response()->json([
            'success' => true,
            'message' => 'Override shift berhasil dihapus.',
        ]);
    }
}
