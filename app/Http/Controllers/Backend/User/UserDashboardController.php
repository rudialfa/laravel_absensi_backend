<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Loan;
use App\Models\Permission;
use App\Models\Schedule;

class UserDashboardController extends Controller
{
    // public function index()
    // {
    //     return view('pages.user.dashboard');
    // }



    //code 2
    public function index()
    {
        $attendanceCount = Attendance::where('user_id', $this->userId())->count();
        $loanPending = Loan::where('user_id', $this->userId())->where('status', 'pending')->count();
        $permissionPending = Permission::where('user_id', $this->userId())->where('is_approved', 0)->count();
        $upcomingSchedules = Schedule::where('user_id', $this->userId())->where('status', 'upcoming')->count();

        return view('pages.user.dashboard', compact(
            'attendanceCount',
            'loanPending',
            'permissionPending',
            'upcomingSchedules'
        ));
    }


    ///tambahan agar tidak error
            private function userId()
        {
            return auth()->id();
        }
}
