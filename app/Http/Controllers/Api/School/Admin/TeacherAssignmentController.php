<?php

namespace App\Http\Controllers\Api\School\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\AttachTeacherRequest;
use App\Models\ClassRoom;
use App\Models\User;

class TeacherAssignmentController extends Controller
{
    /**
     * POST /api/school/admin/classes/{class}/teachers
     * Hubungkan guru ke kelas, sebagai wali_kelas atau guru_mapel.
     */
    public function attach(AttachTeacherRequest $request, ClassRoom $class)
    {
        $this->authorize('update', $class);

        $data = $request->validated();

        $class->classTeachers()->create([
            'user_id'        => $data['user_id'],
            'role_in_class'  => $data['role_in_class'],
            'subject'        => $data['subject'] ?? null,
        ]);

        // Kalau ditandai sebagai wali_kelas, sinkronkan juga ke kolom homeroom_teacher_id
        if ($data['role_in_class'] === 'wali_kelas') {
            $class->update(['homeroom_teacher_id' => $data['user_id']]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Guru berhasil dihubungkan ke kelas',
            'data'    => $class->fresh()->load(['homeroomTeacher:id,name', 'teachers:id,name']),
        ]);
    }

    /**
     * DELETE /api/school/admin/classes/{class}/teachers/{user}
     */
    public function detach(ClassRoom $class, User $user)
    {
        $this->authorize('update', $class);

        $class->classTeachers()->where('user_id', $user->id)->delete();

        if ($class->homeroom_teacher_id === $user->id) {
            $class->update(['homeroom_teacher_id' => null]);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Guru berhasil dilepas dari kelas',
            'data'    => $class->fresh()->load(['homeroomTeacher:id,name', 'teachers:id,name']),
        ]);
    }
}
