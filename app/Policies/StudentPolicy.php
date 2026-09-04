<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * Admin sekolah: boleh lihat/kelola semua murid di company-nya sendiri.
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $student->company_id;
        }

        if ($user->role === 'guru') {
            return $user->teachingClasses()->where('class_rooms.id', $student->class_id)->exists();
        }

        if ($user->role === 'wali') {
            return $user->guardedStudents()->where('students.id', $student->id)->exists();
        }

        return false;
    }

    public function update(User $user, Student $student): bool
    {
        // Hanya admin yang boleh edit data master murid.
        // Guru & wali cuma boleh baca (lihat method view()).
        return $user->role === 'admin' && $user->company_id === $student->company_id;
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->role === 'admin' && $user->company_id === $student->company_id;
    }

    /**
     * Wali boleh mengajukan izin/sakit HANYA kalau terhubung ke murid ini
     * DAN pivot can_submit_permission = true.
     */
    public function submitPermission(User $user, Student $student): bool
    {
        if ($user->role !== 'wali') {
            return false;
        }

        $pivot = $user->guardedStudents()
            ->where('students.id', $student->id)
            ->first();

        return $pivot && $pivot->pivot->can_submit_permission;
    }
}
