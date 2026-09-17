<?php

namespace App\Policies;

use App\Models\LearningMaterial;
use App\Models\User;

class LearningMaterialPolicy
{
    public function view(User $user, LearningMaterial $material): bool
    {
        if ($user->role === 'admin') return $user->company_id === $material->company_id;
        if ($user->role === 'guru') return $user->teachingClasses()->where('class_rooms.id', $material->class_id)->exists();
        if ($user->role === 'wali') return $user->guardedStudents()->whereHas('classRoom', fn($q) => $q->where('id', $material->class_id))->exists();
        return false;
    }

    public function update(User $user, LearningMaterial $material): bool
    {
        return $user->role === 'guru' && $user->id === $material->teacher_id;
    }

    public function delete(User $user, LearningMaterial $material): bool
    {
        return $this->update($user, $material);
    }
}
