<?php

namespace App\Policies;

use App\Models\AcademicEvent;
use App\Models\User;

class AcademicEventPolicy
{
    /**
     * Semua role di sekolah boleh lihat (admin/guru/wali) — kalender
     * dibutuhkan semua orang.
     */
    public function view(User $user, AcademicEvent $event): bool
    {
        return $user->company_id === $event->company_id;
    }

    public function update(User $user, AcademicEvent $event): bool
    {
        return $user->role === 'admin' && $user->company_id === $event->company_id;
    }

    public function delete(User $user, AcademicEvent $event): bool
    {
        return $this->update($user, $event);
    }
}
