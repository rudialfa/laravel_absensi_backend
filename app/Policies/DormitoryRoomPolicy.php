<?php

namespace App\Policies;

use App\Models\DormitoryRoom;
use App\Models\User;

class DormitoryRoomPolicy
{
    /**
     * Kamar tidak punya company_id langsung — otorisasi dicek lewat
     * relasi ke Dormitory-nya (yang punya company_id).
     */
    public function view(User $user, DormitoryRoom $room): bool
    {
        return $user->role === 'admin' && $user->company_id === $room->dormitory->company_id;
    }

    public function update(User $user, DormitoryRoom $room): bool
    {
        return $user->role === 'admin' && $user->company_id === $room->dormitory->company_id;
    }

    public function delete(User $user, DormitoryRoom $room): bool
    {
        return $user->role === 'admin' && $user->company_id === $room->dormitory->company_id;
    }

    /**
     * Dipakai untuk otorisasi assign/move/remove santri di kamar ini.
     */
    public function manageAssignments(User $user, DormitoryRoom $room): bool
    {
        return $user->role === 'admin' && $user->company_id === $room->dormitory->company_id;
    }
}
