<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreAttendanceDeviceRequest;
use App\Models\AttendanceDevice;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttendanceDeviceController extends Controller
{
    /**
     * GET /api/school/admin/devices
     */
    public function index(Request $request)
    {
        $devices = AttendanceDevice::where('company_id', $request->user()->company_id)
            ->with('classRoom:id,name')
            ->orderByDesc('last_seen_at')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data device berhasil diambil',
            'data'    => $devices,
        ]);
    }

    /**
     * POST /api/school/admin/devices
     */
    public function store(StoreAttendanceDeviceRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $request->user()->company_id;
        $data['registered_by'] = $request->user()->id;
        $data['device_token'] = Str::random(config('school.device_token_length', 64));

        $device = AttendanceDevice::create($data);

        // Token cuma ditampilkan sekali saat create — device harus disimpan
        // manual di gadget-nya, karena $hidden menyembunyikannya di response lain
        return response()->json([
            'status'  => true,
            'message' => 'Device berhasil didaftarkan',
            'data'    => [
                'device'       => $device,
                'device_token' => $device->device_token,
            ],
        ], 201);
    }

    /**
     * GET /api/school/admin/devices/{device}
     */
    public function show(AttendanceDevice $device)
    {
        $this->authorize('view', $device);

        return response()->json([
            'status'  => true,
            'message' => 'Detail device berhasil diambil',
            'data'    => $device->load('classRoom:id,name'),
        ]);
    }

    /**
     * PUT /api/school/admin/devices/{device}
     */
    public function update(Request $request, AttendanceDevice $device)
    {
        $this->authorize('update', $device);

        $data = $request->validate([
            'class_id'  => 'nullable|exists:class_rooms,id',
            'name'      => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $device->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Device berhasil diperbarui',
            'data'    => $device->load('classRoom:id,name'),
        ]);
    }

    /**
     * DELETE /api/school/admin/devices/{device}
     */
    public function destroy(AttendanceDevice $device)
    {
        $this->authorize('delete', $device);

        $device->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Device dihapus',
            'data'    => null,
        ]);
    }

    /**
     * POST /api/school/admin/devices/{device}/regenerate-token
     * Generate ulang token — dipakai kalau device hilang/diganti.
     */
    public function regenerateToken(AttendanceDevice $device)
    {
        $this->authorize('update', $device);

        $newToken = Str::random(config('school.device_token_length', 64));
        $device->update(['device_token' => $newToken]);

        return response()->json([
            'status'  => true,
            'message' => 'Token berhasil di-generate ulang',
            'data'    => [
                'device_token' => $newToken,
            ],
        ]);
    }

    /**
     * GET /api/school/admin/attendance-report?from=&to=&class_id=
     * Rekap absen seluruh sekolah — filter by date range & class.
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'from'      => 'required|date',
            'to'        => 'required|date|after_or_equal:from',
            'class_id'  => 'nullable|exists:class_rooms,id',
        ]);

        $report = StudentAttendance::where('company_id', $request->user()->company_id)
            ->whereBetween('date', [$data['from'], $data['to']])
            ->when($data['class_id'] ?? null, fn($q, $classId) => $q->where('class_id', $classId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Rekap absensi berhasil diambil',
            'data'    => $report,
        ]);
    }
}
