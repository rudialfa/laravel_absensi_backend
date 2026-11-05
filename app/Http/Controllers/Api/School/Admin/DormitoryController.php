<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dormitory;
use App\Models\RoomAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class DormitoryController extends Controller
{
    /**
     * GET /api/school/admin/dormitories
     */
    public function index(Request $request)
    {
        $dormitories = Dormitory::where('company_id', $request->user()->company_id)
            ->with('pengasuh:id,name')
            ->withCount('rooms')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data asrama berhasil diambil',
            'data'    => $dormitories,
        ]);
    }

    /**
     * POST /api/school/admin/dormitories
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'gender'       => 'required|in:L,P',
            'pengasuh_id'  => 'nullable|exists:users,id',
            'address'      => 'nullable|string',
        ]);

        $data['company_id'] = $request->user()->company_id;
        $this->ensurePengasuhSameSchool($data['pengasuh_id'] ?? null, $data['company_id']);

        $dormitory = Dormitory::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Asrama berhasil dibuat',
            'data'    => $dormitory,
        ], 201);
    }

    /**
     * GET /api/school/admin/dormitories/{dormitory}
     */
    public function show(Dormitory $dormitory)
    {
        $this->authorize('view', $dormitory);

        $dormitory->load(['pengasuh:id,name', 'rooms' => function ($q) {
            $q->withCount('activeAssignments');
        }]);

        return response()->json([
            'status'  => true,
            'message' => 'Detail asrama berhasil diambil',
            'data'    => $dormitory,
        ]);
    }

    /**
     * PUT /api/school/admin/dormitories/{dormitory}
     */
    public function update(Request $request, Dormitory $dormitory)
    {
        $this->authorize('update', $dormitory);

        $data = $request->validate([
            'name'         => 'sometimes|string|max:100',
            'gender'       => 'sometimes|in:L,P',
            'pengasuh_id'  => 'nullable|exists:users,id',
            'address'      => 'nullable|string',
            'is_active'    => 'sometimes|boolean',
        ]);

        if (array_key_exists('pengasuh_id', $data)) {
            $this->ensurePengasuhSameSchool($data['pengasuh_id'], $dormitory->company_id);
        }

        // Ganti putra/putri hanya boleh kalau asrama sedang kosong
        if (isset($data['gender']) && $data['gender'] !== $dormitory->gender) {
            abort_if($this->occupantCount($dormitory) > 0, 422, 'Asrama masih dihuni, jenis putra/putri tidak bisa diubah');
        }

        $dormitory->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Asrama berhasil diperbarui',
            'data'    => $dormitory,
        ]);
    }

    /**
     * DELETE /api/school/admin/dormitories/{dormitory}
     */
    public function destroy(Dormitory $dormitory)
    {
        $this->authorize('delete', $dormitory);

        // Kamar & riwayat penempatan ikut terhapus (cascade) — tolak kalau masih ada penghuni
        abort_if(
            $this->occupantCount($dormitory) > 0,
            422,
            'Asrama masih memiliki penghuni aktif, pindahkan santri terlebih dahulu'
        );

        $dormitory->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Asrama dihapus',
            'data'    => null,
        ]);
    }

    private function occupantCount(Dormitory $dormitory): int
    {
        return RoomAssignment::whereNull('moved_out_at')
            ->whereIn('room_id', $dormitory->rooms()->select('id'))
            ->count();
    }

    /** Pengasuh harus akun dari sekolah yang sama */
    private function ensurePengasuhSameSchool($pengasuhId, $companyId): void
    {
        if (!$pengasuhId) {
            return;
        }

        abort_unless(
            User::where('id', $pengasuhId)->where('company_id', $companyId)->exists(),
            422,
            'Pengasuh harus akun dari sekolah ini'
        );
    }
}
