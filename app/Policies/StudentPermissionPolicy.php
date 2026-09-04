<?php

namespace App\Policies;

use App\Models\StudentPermission;
use App\Models\User;

class StudentPermissionPolicy
{
    /**
     * Admin: semua pengajuan di company-nya.
     * Guru: hanya pengajuan dari murid di kelas yang dia ampu.
     */
    public function review(User $user, StudentPermission $permission): bool
    {
        $permission->loadMissing('student');

        if ($user->role === 'admin') {
            return $user->company_id === $permission->student->company_id;
        }

        if ($user->role === 'guru') {
            return $user->teachingClasses()
                ->where('class_rooms.id', $permission->student->class_id)
                ->exists();
        }

        return false;
    }
}
