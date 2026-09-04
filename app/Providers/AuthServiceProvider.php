<?php

namespace App\Providers;

use App\Models\AttendanceDevice;
use App\Models\ClassRoom;
use App\Models\Student;
use App\Models\StudentPermission;
use App\Models\User;
use App\Policies\AttendanceDevicePolicy;
use App\Policies\ClassRoomPolicy;
use App\Policies\StudentPermissionPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Student::class           => StudentPolicy::class,
        ClassRoom::class         => ClassRoomPolicy::class,
        AttendanceDevice::class  => AttendanceDevicePolicy::class,
        StudentPermission::class => StudentPermissionPolicy::class,
        // ... policy company/pesantren yang sudah ada (kalau ada) tetap di sini
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        /**
         * Admin sekolah boleh kelola (lihat/edit/reset password/nonaktifkan) staff
         * (guru/wali) HANYA di sekolahnya sendiri. Guru/wali tidak pernah bisa
         * kelola staff lain, bahkan sesama guru/wali.
         */
        Gate::define('manage-staff', function (User $user, User $staff) {
            return $user->role === 'admin' && $user->company_id === $staff->company_id;
        });
    }
}
