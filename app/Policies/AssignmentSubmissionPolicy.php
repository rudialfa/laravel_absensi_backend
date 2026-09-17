<?php

namespace App\Policies;

use App\Models\AssignmentSubmission;
use App\Models\User;

class AssignmentSubmissionPolicy
{
    public function view(User $user, AssignmentSubmission $submission): bool
    {
        if ($user->role === 'guru') {
            return $user->id === $submission->assignment->teacher_id
                || $user->teachingClasses()->where('class_rooms.id', $submission->assignment->class_id)->exists();
        }

        if ($user->role === 'wali') {
            return $user->guardedStudents()->where('students.id', $submission->student_id)->exists();
        }

        return false;
    }

    public function grade(User $user, AssignmentSubmission $submission): bool
    {
        return $user->role === 'guru' && $user->id === $submission->assignment->teacher_id;
    }
}
