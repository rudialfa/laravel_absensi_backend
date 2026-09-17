<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Models\DormitoryRoom;
use App\Models\RoomAssignment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomAssignmentController extends Controller
{
    /**
     * POST /api/school/admin/rooms/{room}/assign
     * Tempatkan santri ke kamar ini (santri belum punya penempatan aktif).
     */
    public function assign(Request $request, DormitoryRoom $room)
    {
        $this->authorize('manageAssignments', $room);

        $data = $request->validate([
            'student_id'  => 'required|exists:students,id',
            'moved_in_at' => 'nullable|date',
            'notes'       => 'nullable|string',
        ]);

        $student = Student::findOrFail($data['student_id']);

        abort_if(
            $student->company_id !== $room->dormitory->company_id,
            403,
            'Santri ini bukan dari sekolah yang sama dengan asrama'
        );

        abort_if(
            !$student->is_boarding,
            422,
            'Santri ini tidak berstatus boarding'
        );

        abort_if(
            $student->activeRoomAssignment,
            422,
            'Santri ini sudah punya penempatan kamar aktif, gunakan fitur pindah kamar'
        );

        abort_if(
            $room->available_slots <= 0,
            422,
            'Kamar ini sudah penuh'
        );

        $assignment = RoomAssignment::create([
            'student_id'  => $student->id,
            'room_id'     => $room->id,
            'moved_in_at' => $data['moved_in_at'] ?? now()->toDateString(),
            'assigned_by' => $request->user()->id,
            'notes'       => $data['notes'] ?? null,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Santri berhasil ditempatkan di kamar',
            'data'    => $assignment->load(['student:id,name,nis', 'room:id,name']),
        ], 201);
    }

    /**
     * POST /api/school/admin/students/{student}/move-room
     * Pindahkan santri dari kamar sekarang ke kamar baru — keluarkan
     * dari kamar lama & tempatkan ke kamar baru dalam 1 transaksi.
     */
    public function moveRoom(Request $request, Student $student)
    {
        $data = $request->validate([
            'new_room_id' => 'required|exists:dormitory_rooms,id',
            'notes'       => 'nullable|string',
        ]);

        $newRoom = DormitoryRoom::findOrFail($data['new_room_id']);
        $this->authorize('manageAssignments', $newRoom);

        abort_if(
            $student->company_id !== $newRoom->dormitory->company_id,
            403,
            'Santri ini bukan dari sekolah yang sama dengan asrama tujuan'
        );

        $current = $student->activeRoomAssignment;

        abort_if(!$current, 422, 'Santri ini belum punya penempatan kamar aktif');

        abort_if(
            $current->room_id === $newRoom->id,
            422,
            'Santri sudah berada di kamar ini'
        );

        abort_if(
            $newRoom->available_slots <= 0,
            422,
            'Kamar tujuan sudah penuh'
        );

        $newAssignment = DB::transaction(function () use ($current, $newRoom, $student, $request, $data) {
            $current->update(['moved_out_at' => now()->toDateString()]);

            return RoomAssignment::create([
                'student_id'  => $student->id,
                'room_id'     => $newRoom->id,
                'moved_in_at' => now()->toDateString(),
                'assigned_by' => $request->user()->id,
                'notes'       => $data['notes'] ?? null,
            ]);
        });

        return response()->json([
            'status'  => true,
            'message' => 'Santri berhasil dipindahkan kamar',
            'data'    => $newAssignment->load(['student:id,name,nis', 'room:id,name']),
        ]);
    }

    /**
     * POST /api/school/admin/assignments/{assignment}/checkout
     * Keluarkan santri dari asrama (tanpa pindah ke kamar lain) —
     * misal santri keluar dari program boarding sepenuhnya.
     */
    public function checkout(Request $request, RoomAssignment $assignment)
    {
        $this->authorize('manageAssignments', $assignment->room);

        abort_if($assignment->moved_out_at, 422, 'Penempatan ini sudah selesai sebelumnya');

        $data = $request->validate([
            'moved_out_at' => 'nullable|date',
        ]);

        $assignment->update([
            'moved_out_at' => $data['moved_out_at'] ?? now()->toDateString(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Santri berhasil dikeluarkan dari kamar',
            'data'    => $assignment,
        ]);
    }

    /**
     * GET /api/school/admin/dormitories/{dormitory}/occupants
     * Daftar semua penghuni aktif di 1 asrama (lintas kamar).
     */
    public function occupants(\App\Models\Dormitory $dormitory)
    {
        $this->authorize('view', $dormitory);

        $occupants = RoomAssignment::active()
            ->whereHas('room', fn($q) => $q->where('dormitory_id', $dormitory->id))
            ->with(['student:id,name,nis,class_id', 'student.classRoom:id,name', 'room:id,name'])
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Data penghuni asrama berhasil diambil',
            'data'    => $occupants,
        ]);
    }

    /**
     * GET /api/school/admin/students/boarding-unassigned
     * Santri boarding yang belum ditempatkan di kamar manapun —
     * membantu admin melihat siapa yang masih perlu ditempatkan.
     */
    public function unassignedBoardingStudents(Request $request)
    {
        $students = Student::where('company_id', $request->user()->company_id)
            ->where('is_boarding', true)
            ->where('is_active', true)
            ->whereDoesntHave('roomAssignments', fn($q) => $q->whereNull('moved_out_at'))
            ->orderBy('name')
            ->get(['id', 'name', 'nis', 'class_id']);

        return response()->json([
            'status'  => true,
            'message' => 'Data santri belum ditempatkan berhasil diambil',
            'data'    => $students,
        ]);
    }
}
