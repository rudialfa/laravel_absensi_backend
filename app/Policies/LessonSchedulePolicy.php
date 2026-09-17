<?php

namespace App\Policies;

use App\Models\LessonSchedule;
use App\Models\User;

class LessonSchedulePolicy
{
    public function view(User $user, LessonSchedule $schedule): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $schedule->company_id;
        }

        if ($user->role === 'guru') {
            return $user->id === $schedule->teacher_id
                || $user->teachingClasses()->where('class_rooms.id', $schedule->class_id)->exists();
        }

        return false;
    }

    public function update(User $user, LessonSchedule $schedule): bool
    {
        return $user->role === 'admin' && $user->company_id === $schedule->company_id;
    }

    public function delete(User $user, LessonSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
