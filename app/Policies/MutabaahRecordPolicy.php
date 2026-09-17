<?php

namespace App\Policies;

use App\Models\MutabaahRecord;
use App\Models\User;

class MutabaahRecordPolicy
{
    public function view(User $user, MutabaahRecord $record): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $record->company_id;
        }

        if ($user->role === 'guru') {
            return $user->teachingClasses()
                ->where('class_rooms.id', $record->student->class_id)
                ->exists();
        }

        return false;
    }

    public function update(User $user, MutabaahRecord $record): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $record->company_id;
        }

        return $user->role === 'guru' && $user->id === $record->recorded_by;
    }

    public function delete(User $user, MutabaahRecord $record): bool
    {
        return $this->update($user, $record);
    }
}
