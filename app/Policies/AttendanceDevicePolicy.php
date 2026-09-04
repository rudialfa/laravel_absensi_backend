<?php

namespace App\Policies;

use App\Models\AttendanceDevice;
use App\Models\User;

class AttendanceDevicePolicy
{
    // Device kiosk cuma dikelola admin sekolah — guru/wali tidak pernah
    // akses langsung endpoint ini (mereka pakai endpoint kiosk device-token).
    public function view(User $user, AttendanceDevice $device): bool
    {
        return $user->role === 'admin' && $user->company_id === $device->company_id;
    }

    public function update(User $user, AttendanceDevice $device): bool
    {
        return $this->view($user, $device);
    }

    public function delete(User $user, AttendanceDevice $device): bool
    {
        return $this->view($user, $device);
    }
}
