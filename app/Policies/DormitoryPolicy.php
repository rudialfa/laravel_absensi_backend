<?php

namespace App\Policies;

use App\Models\Dormitory;
use App\Models\User;

class DormitoryPolicy
{
    /**
     * Untuk tahap ini, kelola asrama (CRUD + kelola kamar & penempatan)
     * hanya boleh oleh admin sekolah. Role "Pengasuh" khusus asrama
     * (disebut di master context) belum dibuat sebagai role terpisah —
     * kalau nanti dibutuhkan, cukup tambah kondisi baru di sini tanpa
     * ubah struktur policy.
     */
    public function view(User $user, Dormitory $dormitory): bool
    {
        return $user->role === 'admin' && $user->company_id === $dormitory->company_id;
    }

    public function update(User $user, Dormitory $dormitory): bool
    {
        return $user->role === 'admin' && $user->company_id === $dormitory->company_id;
    }

    public function delete(User $user, Dormitory $dormitory): bool
    {
        return $user->role === 'admin' && $user->company_id === $dormitory->company_id;
    }

    /**
     * Dipakai untuk otorisasi kelola kamar (create/update/delete room)
     * dan kelola penempatan santri (assign/move/remove) di bawah asrama ini.
     */
    public function manageRooms(User $user, Dormitory $dormitory): bool
    {
        return $user->role === 'admin' && $user->company_id === $dormitory->company_id;
    }
}
