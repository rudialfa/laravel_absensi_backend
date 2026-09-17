<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;

class AssignmentPolicy
{
    public function view(User $user, Assignment $assignment): bool
    {
        if ($user->role === 'admin') return $user->company_id === $assignment->company_id;
        if ($user->role === 'guru') return $user->teachingClasses()->where('class_rooms.id', $assignment->class_id)->exists();
        if ($user->role === 'wali') return $user->guardedStudents()->whereHas('classRoom', fn($q) => $q->where('id', $assignment->class_id))->exists();
        return false;
    }

    public function update(User $user, Assignment $assignment): bool
    {
        return $user->role === 'guru' && $user->id === $assignment->teacher_id;
    }

    public function delete(User $user, Assignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
