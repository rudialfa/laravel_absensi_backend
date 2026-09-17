<?php

namespace App\Policies;

use App\Models\UksVisit;
use App\Models\User;

class UksVisitPolicy
{
    public function view(User $user, UksVisit $visit): bool
    {
        if ($user->role === 'admin') return $user->company_id === $visit->company_id;
        if ($user->role === 'wali') return $user->guardedStudents()->where('students.id', $visit->student_id)->exists();
        return $user->role === 'guru';
    }

    public function manage(User $user, UksVisit $visit): bool
    {
        return in_array($user->role, ['admin', 'guru']) && $user->company_id === $visit->company_id;
    }
}
