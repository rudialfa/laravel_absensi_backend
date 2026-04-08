<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;


class UserAttendanceController extends Controller
{
    //   public function index(Request $request)
    // {
    //     $userId = Auth::id();

    //     // Filter by date (optional)
    //     $startDate = $request->start_date;
    //     $endDate   = $request->end_date;

    //     $query = Attendance::where('user_id', $userId);

    //     if ($startDate) {
    //         $query->whereDate('date', '>=', $startDate);
    //     }

    //     if ($endDate) {
    //         $query->whereDate('date', '<=', $endDate);
    //     }

    //     $attendances = $query->orderBy('date', 'desc')->paginate(20);

    //     return view('pages.user.attendances.index', compact('attendances', 'startDate', 'endDate'));
    // }

    //code 2
      public function index(Request $request)
    {
        $query = Attendance::where('user_id', $this->userId())
            ->where('company_id', $this->companyId());

        if ($request->filled('start_date')) {
            $query->whereDate('date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('date', '<=', $request->end_date);
        }

        if ($request->filled('month')) {
            $query->whereMonth('date', $request->month);
        }

        if ($request->filled('year')) {
            $query->whereYear('date', $request->year);
        }

        $attendances = $query->orderByDesc('date')->paginate(20);

        return view('pages.user.attendances.index', compact('attendances'));
    }
}
