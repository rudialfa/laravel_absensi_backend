<?php

namespace App\Policies;

use App\Models\BoardingPermission;
use App\Models\User;

class BoardingPermissionPolicy
{
    public function view(User $user, BoardingPermission $permission): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $permission->company_id;
        }

        if ($user->role === 'wali') {
            return $user->guardedStudents()
                ->where('students.id', $permission->student_id)
                ->exists();
        }

        return false;
    }

    /**
     * Wali boleh ajukan izin keluar/pulang HANYA kalau terhubung ke murid ini
     * DAN pivot can_submit_permission = true (reuse aturan yang sama seperti
     * izin/sakit sekolah biasa di StudentPolicy).
     */
    public function submit(User $user, \App\Models\Student $student): bool
    {
        if ($user->role !== 'wali') {
            return false;
        }

        $pivot = $user->guardedStudents()->where('students.id', $student->id)->first();

        return $pivot && $pivot->pivot->can_submit_permission;
    }

    /**
     * Approve/reject/checkin hanya admin sekolah.
     */
    public function review(User $user, BoardingPermission $permission): bool
    {
        return $user->role === 'admin' && $user->company_id === $permission->company_id;
    }
}
