<?php

namespace App\Policies;

use App\Models\Curriculum;
use App\Models\User;

class CurriculumPolicy
{
    /**
     * Admin & guru boleh lihat (guru butuh referensi buat jadwal/nilai).
     * Cuma admin yang boleh kelola.
     */
    public function view(User $user, Curriculum $curriculum): bool
    {
        return $user->company_id === $curriculum->company_id;
    }

    public function update(User $user, Curriculum $curriculum): bool
    {
        return $user->role === 'admin' && $user->company_id === $curriculum->company_id;
    }

    public function delete(User $user, Curriculum $curriculum): bool
    {
        return $this->update($user, $curriculum);
    }
}
