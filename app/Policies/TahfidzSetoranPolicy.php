<?php

namespace App\Policies;

use App\Models\TahfidzSetoran;
use App\Models\User;

class TahfidzSetoranPolicy
{
    /**
     * Admin: lihat semua setoran di sekolahnya.
     * Guru: lihat setoran murid di kelas yang diampu saja.
     */
    public function view(User $user, TahfidzSetoran $setoran): bool
    {
        if ($user->role === 'admin') {
            return $user->company_id === $setoran->company_id;
        }

        if ($user->role === 'guru') {
            return $user->teachingClasses()
                ->where('class_rooms.id', $setoran->student->class_id)
                ->exists();
        }

        return false;
    }

    /**
     * Cuma guru pembimbing yang input setoran itu sendiri yang boleh edit/hapus
     * (mencegah guru lain mengubah catatan guru lain).
     */
    public function update(User $user, TahfidzSetoran $setoran): bool
    {
        return $user->role === 'guru' && $user->id === $setoran->guru_id;
    }

    public function delete(User $user, TahfidzSetoran $setoran): bool
    {
        return $this->update($user, $setoran);
    }
}
