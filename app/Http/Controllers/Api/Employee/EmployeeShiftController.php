<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Models\ShiftGroupAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\UserShiftOverride;

class EmployeeShiftController extends Controller
{

    // // kode 2
    // private function user(Request $request)
    // {
    //     // Paling aman untuk Sanctum API
    //     return $request->user();
    // }

    // private function companyId(Request $request): int
    // {
    //     return (int) $this->user($request)->company_id;
    // }

    // private function userId(Request $request): int
    // {
    //     return (int) $this->user($request)->id;
    // }

    // /**
    //  * Resolve shift by date with priority:
    //  * 1) user_shift_overrides (active + date in range)
    //  * 2) shift group assignment (date in range) based on group membership
    //  * 3) default shift (optional fallback)
    //  */
    // private function resolveShiftByDate(Request $request, string $date): array
    // {
    //     $companyId = $this->companyId($request);
    //     $userId = $this->userId($request);
    //     $user = $this->user($request);

    //     $d = Carbon::parse($date)->toDateString();

    //     /**
    //      * 1) Override user (paling prioritas)
    //      */
    //     $override = UserShiftOverride::query()
    //         ->where('company_id', $companyId)
    //         ->where('user_id', $userId)
    //         ->where('status', 'active')
    //         ->whereDate('start_date', '<=', $d)
    //         ->where(function ($q) use ($d) {
    //             $q->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
    //         })
    //         ->with(['shift:id,name,start_time,end_time,grace_minutes,is_default'])
    //         ->orderBy('start_date', 'desc')
    //         ->first();

    //     if ($override && $override->shift) {
    //         return [
    //             'source' => 'override',
    //             'shift' => $override->shift,
    //             'meta' => [
    //                 'override_id' => $override->id,
    //                 'start_date' => optional($override->start_date)->toDateString(),
    //                 'end_date' => optional($override->end_date)->toDateString(),
    //                 'reason' => $override->reason,
    //             ],
    //         ];
    //     }

    //     /**
    //      * 2) Group assignment based on group membership
    //      *    Membership pivot shift_group_users.start_date/end_date opsional.
    //      */
    //     $groupIds = $user->shiftGroups()
    //         ->where(function ($q) use ($d) {
    //             $q->whereNull('shift_group_users.start_date')
    //                 ->orWhereDate('shift_group_users.start_date', '<=', $d);
    //         })
    //         ->where(function ($q) use ($d) {
    //             $q->whereNull('shift_group_users.end_date')
    //                 ->orWhereDate('shift_group_users.end_date', '>=', $d);
    //         })
    //         ->pluck('shift_groups.id')
    //         ->toArray();

    //     if (!empty($groupIds)) {
    //         $assignment = ShiftGroupAssignment::query()
    //             ->where('company_id', $companyId)
    //             ->whereIn('shift_group_id', $groupIds)
    //             ->whereDate('start_date', '<=', $d)
    //             ->where(function ($q) use ($d) {
    //                 $q->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
    //             })
    //             ->with([
    //                 'shift:id,name,start_time,end_time,grace_minutes,is_default',
    //                 'group:id,name',
    //             ])
    //             ->orderBy('start_date', 'desc')
    //             ->first();

    //         if ($assignment && $assignment->shift) {
    //             return [
    //                 'source' => 'group_assignment',
    //                 'shift' => $assignment->shift,
    //                 'meta' => [
    //                     'assignment_id' => $assignment->id,
    //                     'shift_group_id' => $assignment->shift_group_id,
    //                     'shift_group_name' => optional($assignment->group)->name,
    //                     'start_date' => optional($assignment->start_date)->toDateString(),
    //                     'end_date' => optional($assignment->end_date)->toDateString(),
    //                     'note' => $assignment->note,
    //                 ],
    //             ];
    //         }
    //     }

    //     /**
    //      * 3) Default shift fallback (opsional)
    //      */
    //     $defaultShift = Shift::query()
    //         ->where('company_id', $companyId)
    //         ->where('is_default', true)
    //         ->select('id', 'name', 'start_time', 'end_time', 'grace_minutes', 'is_default')
    //         ->first();

    //     if ($defaultShift) {
    //         return [
    //             'source' => 'default_shift',
    //             'shift' => $defaultShift,
    //             'meta' => null,
    //         ];
    //     }

    //     return [
    //         'source' => 'none',
    //         'shift' => null,
    //         'meta' => null,
    //     ];
    // }

    // /**
    //  * GET /api/company/employee/shifts/today
    //  */
    // public function today(Request $request)
    // {
    //     $today = Carbon::now()->toDateString();
    //     $resolved = $this->resolveShiftByDate($request, $today);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Shift aktif hari ini',
    //         'data' => [
    //             'date' => $today,
    //             'source' => $resolved['source'],
    //             'shift' => $resolved['shift'],
    //             'meta' => $resolved['meta'],
    //         ],
    //     ]);
    // }

    // /**
    //  * GET /api/company/employee/shifts/date/{date}
    //  * date format: YYYY-MM-DD
    //  */
    // public function byDate(Request $request, $date)
    // {
    //     try {
    //         $parsed = Carbon::parse($date)->toDateString();
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Format date tidak valid. Gunakan YYYY-MM-DD.',
    //         ], 422);
    //     }

    //     $resolved = $this->resolveShiftByDate($request, $parsed);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Shift aktif untuk tanggal tersebut',
    //         'data' => [
    //             'date' => $parsed,
    //             'source' => $resolved['source'],
    //             'shift' => $resolved['shift'],
    //             'meta' => $resolved['meta'],
    //         ],
    //     ]);
    // }

    // /**
    //  * GET /api/company/employee/shifts/schedule?start=YYYY-MM-DD&end=YYYY-MM-DD
    //  * Max range 62 hari biar tidak berat.
    //  */
    // public function schedule(Request $request)
    // {
    //     $validated = $request->validate([
    //         'start' => ['required', 'date'],
    //         'end' => ['required', 'date', 'after_or_equal:start'],
    //     ]);

    //     $start = Carbon::parse($validated['start'])->startOfDay();
    //     $end = Carbon::parse($validated['end'])->startOfDay();

    //     if ($start->diffInDays($end) > 62) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Range maksimal 62 hari.',
    //         ], 422);
    //     }

    //     $items = [];
    //     $cursor = $start->copy();

    //     while ($cursor->lte($end)) {
    //         $d = $cursor->toDateString();
    //         $resolved = $this->resolveShiftByDate($request, $d);

    //         $items[] = [
    //             'date' => $d,
    //             'source' => $resolved['source'],
    //             'shift' => $resolved['shift'],
    //             'meta' => $resolved['meta'],
    //         ];

    //         $cursor->addDay();
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Jadwal shift',
    //         'data' => [
    //             'start' => $start->toDateString(),
    //             'end' => $end->toDateString(),
    //             'items' => $items,
    //         ],
    //     ]);
    // }

    // kode 3

    private function user(Request $request)
    {
        return $request->user();
    }

    private function companyId(Request $request): int
    {
        return (int) $this->user($request)->company_id;
    }

    private function userId(Request $request): int
    {
        return (int) $this->user($request)->id;
    }

    private function resolveShiftByDate(Request $request, string $date): array
    {
        $companyId = $this->companyId($request);
        $userId = $this->userId($request);
        $user = $this->user($request);

        $d = Carbon::parse($date)->toDateString();

        /**
         * 1) Override user (paling prioritas)
         */
        $override = UserShiftOverride::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
            })
            // ✅ FIX: grace_minutes → grace_period_minutes
            ->with(['shift:id,name,start_time,end_time,grace_period_minutes,is_default'])
            ->orderBy('start_date', 'desc')
            ->first();

        if ($override && $override->shift) {
            return [
                'source' => 'override',
                'shift' => $override->shift,
                'meta' => [
                    'override_id' => $override->id,
                    'start_date' => optional($override->start_date)->toDateString(),
                    'end_date' => optional($override->end_date)->toDateString(),
                    'reason' => $override->reason,
                ],
            ];
        }

        /**
         * 2) Group assignment based on group membership
         */
        $groupIds = $user->shiftGroups()
            ->where(function ($q) use ($d) {
                $q->whereNull('shift_group_users.start_date')
                    ->orWhereDate('shift_group_users.start_date', '<=', $d);
            })
            ->where(function ($q) use ($d) {
                $q->whereNull('shift_group_users.end_date')
                    ->orWhereDate('shift_group_users.end_date', '>=', $d);
            })
            ->pluck('shift_groups.id')
            ->toArray();

        if (!empty($groupIds)) {
            $assignment = ShiftGroupAssignment::query()
                ->where('company_id', $companyId)
                ->whereIn('shift_group_id', $groupIds)
                ->whereDate('start_date', '<=', $d)
                ->where(function ($q) use ($d) {
                    $q->whereNull('end_date')->orWhereDate('end_date', '>=', $d);
                })
                ->with([
                    // ✅ FIX: grace_minutes → grace_period_minutes
                    'shift:id,name,start_time,end_time,grace_period_minutes,is_default',
                    'group:id,name',
                ])
                ->orderBy('start_date', 'desc')
                ->first();

            if ($assignment && $assignment->shift) {
                return [
                    'source' => 'group_assignment',
                    'shift' => $assignment->shift,
                    'meta' => [
                        'assignment_id' => $assignment->id,
                        'shift_group_id' => $assignment->shift_group_id,
                        'shift_group_name' => optional($assignment->group)->name,
                        'start_date' => optional($assignment->start_date)->toDateString(),
                        'end_date' => optional($assignment->end_date)->toDateString(),
                        'note' => $assignment->note,
                    ],
                ];
            }
        }

        /**
         * 3) Default shift fallback
         */
        $defaultShift = Shift::query()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            // ✅ FIX: grace_minutes → grace_period_minutes
            ->select('id', 'name', 'start_time', 'end_time', 'grace_period_minutes', 'is_default')
            ->first();

        if ($defaultShift) {
            return [
                'source' => 'default_shift',
                'shift' => $defaultShift,
                'meta' => null,
            ];
        }

        return [
            'source' => 'none',
            'shift' => null,
            'meta' => null,
        ];
    }

    /**
     * GET /api/company/employee/shifts/today
     */
    public function today(Request $request)
    {
        $today = Carbon::now()->toDateString();
        $resolved = $this->resolveShiftByDate($request, $today);

        return response()->json([
            'success' => true,
            'message' => 'Shift aktif hari ini',
            'data' => [
                'date' => $today,
                'source' => $resolved['source'],
                'shift' => $resolved['shift'],
                'meta' => $resolved['meta'],
            ],
        ]);
    }

    /**
     * GET /api/company/employee/shifts/date/{date}
     */
    public function byDate(Request $request, $date)
    {
        try {
            $parsed = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format date tidak valid. Gunakan YYYY-MM-DD.',
            ], 422);
        }

        $resolved = $this->resolveShiftByDate($request, $parsed);

        return response()->json([
            'success' => true,
            'message' => 'Shift aktif untuk tanggal tersebut',
            'data' => [
                'date' => $parsed,
                'source' => $resolved['source'],
                'shift' => $resolved['shift'],
                'meta' => $resolved['meta'],
            ],
        ]);
    }

    /**
     * GET /api/company/employee/shifts/schedule?start=YYYY-MM-DD&end=YYYY-MM-DD
     */
    public function schedule(Request $request)
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);

        $start = Carbon::parse($validated['start'])->startOfDay();
        $end = Carbon::parse($validated['end'])->startOfDay();

        if ($start->diffInDays($end) > 62) {
            return response()->json([
                'success' => false,
                'message' => 'Range maksimal 62 hari.',
            ], 422);
        }

        $items = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $resolved = $this->resolveShiftByDate($request, $d);

            $items[] = [
                'date' => $d,
                'source' => $resolved['source'],
                'shift' => $resolved['shift'],
                'meta' => $resolved['meta'],
            ];

            $cursor->addDay();
        }

        return response()->json([
            'success' => true,
            'message' => 'Jadwal shift',
            'data' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'items' => $items,
            ],
        ]);
    }
}
