<?php

namespace App\Policies;

use App\Models\HealthRecord;
use App\Models\User;

class HealthRecordPolicy
{
    public function view(User $user, HealthRecord $record): bool
    {
        if ($user->role === 'admin') return $user->company_id === $record->company_id;
        if ($user->role === 'wali') return $user->guardedStudents()->where('students.id', $record->student_id)->exists();
        return $user->role === 'guru';
    }

    public function manage(User $user, HealthRecord $record): bool
    {
        return in_array($user->role, ['admin', 'guru']) && $user->company_id === $record->company_id;
    }
}
