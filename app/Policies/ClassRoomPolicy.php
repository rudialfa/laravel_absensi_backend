<?php

namespace App\Policies;

use App\Models\ClassRoom;
use App\Models\User;

class ClassRoomPolicy
{
    public function view(User $user, ClassRoom $classRoom): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $classRoom->company_id;
        }

        if ($user->role === 'guru') {
            return $user->teachingClasses()->where('class_rooms.id', $classRoom->id)->exists();
        }

        return false;
    }

    /**
     * Guru boleh input/edit absen di kelas yang dia ampu.
     * Ini yang dipakai controller absen (kiosk manual & dashboard guru).
     */
    public function recordAttendance(User $user, ClassRoom $classRoom): bool
    {
        if ($user->role !== 'guru') {
            return false;
        }

        return $user->teachingClasses()->where('class_rooms.id', $classRoom->id)->exists();
    }

    public function update(User $user, ClassRoom $classRoom): bool
    {
        return $user->role === 'admin' && $user->company_id === $classRoom->company_id;
    }

    public function delete(User $user, ClassRoom $classRoom): bool
    {
        return $user->role === 'admin' && $user->company_id === $classRoom->company_id;
    }
}
