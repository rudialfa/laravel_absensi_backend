<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    /**
     * Semua role di sekolah (admin/guru/wali) boleh LIHAT daftar mapel —
     * dibutuhkan buat dropdown di banyak tempat (jadwal, tugas, nilai, dst).
     * Hanya admin yang boleh kelola (create/update/delete).
     */
    public function view(User $user, Subject $subject): bool
    {
        return $user->company_id === $subject->company_id;
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->role === 'admin' && $user->company_id === $subject->company_id;
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $this->update($user, $subject);
    }
}
