<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dormitory;
use App\Models\DormitoryRoom;
use Illuminate\Http\Request;

class DormitoryRoomController extends Controller
{
    /**
     * GET /api/school/admin/dormitories/{dormitory}/rooms
     */
    public function index(Dormitory $dormitory)
    {
        $this->authorize('view', $dormitory);

        $rooms = $dormitory->rooms()
            ->withCount('activeAssignments')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data kamar berhasil diambil',
            'data'    => $rooms,
        ]);
    }

    /**
     * POST /api/school/admin/dormitories/{dormitory}/rooms
     */
    public function store(Request $request, Dormitory $dormitory)
    {
        $this->authorize('manageRooms', $dormitory);

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'capacity' => 'required|integer|min:1|max:50',
        ]);

        $data['dormitory_id'] = $dormitory->id;

        $room = DormitoryRoom::create($data);

        return response()->json([
            'status'  => true,
            'message' => 'Kamar berhasil dibuat',
            'data'    => $room,
        ], 201);
    }

    /**
     * GET /api/school/admin/rooms/{room}
     */
    public function show(DormitoryRoom $room)
    {
        $this->authorize('view', $room);

        $room->load(['dormitory:id,name', 'activeAssignments.student:id,name,nis']);

        return response()->json([
            'status'  => true,
            'message' => 'Detail kamar berhasil diambil',
            'data'    => $room,
        ]);
    }

    /**
     * PUT /api/school/admin/rooms/{room}
     */
    public function update(Request $request, DormitoryRoom $room)
    {
        $this->authorize('update', $room);

        $data = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'capacity'  => 'sometimes|integer|min:1|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $room->update($data);

        return response()->json([
            'status'  => true,
            'message' => 'Kamar berhasil diperbarui',
            'data'    => $room,
        ]);
    }

    /**
     * DELETE /api/school/admin/rooms/{room}
     */
    public function destroy(DormitoryRoom $room)
    {
        $this->authorize('delete', $room);

        abort_if(
            $room->activeAssignments()->exists(),
            422,
            'Kamar masih memiliki penghuni aktif, pindahkan santri terlebih dahulu'
        );

        $room->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Kamar dihapus',
            'data'    => null,
        ]);
    }
}
