<?php

namespace App\Policies;

use App\Models\Grade;
use App\Models\User;

class GradePolicy
{
    public function view(User $user, Grade $grade): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $grade->company_id;
        }

        if ($user->role === 'guru') {
            return $user->teachingClasses()->where('class_rooms.id', $grade->class_id)->exists();
        }

        if ($user->role === 'wali') {
            return $user->guardedStudents()->where('students.id', $grade->student_id)->exists();
        }

        return false;
    }

    /**
     * Cuma guru yang menginput nilai itu sendiri yang boleh edit/hapus.
     */
    public function update(User $user, Grade $grade): bool
    {
        return $user->role === 'guru' && $user->id === $grade->teacher_id;
    }

    public function delete(User $user, Grade $grade): bool
    {
        return $this->update($user, $grade);
    }
}
