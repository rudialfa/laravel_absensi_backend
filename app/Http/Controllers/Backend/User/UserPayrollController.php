<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payrool;
use Illuminate\Support\Facades\Auth;

class UserPayrollController extends Controller
{
    // public function index()
    // {
    //     $payrolls = Payrool::where('user_id', Auth::id())
    //         ->orderBy('period_end', 'desc')
    //         ->paginate(10);

    //     return view('pages.user.payrolls.index', compact('payrolls'));
    // }


    //code 2 index
     public function index(Request $request)
    {
        $query = Payrool::where('user_id', $this->userId())
            ->where('company_id', $this->companyId());

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('period_start', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('period_end', '<=', $request->to);
        }

        $payrolls = $query->orderByDesc('period_end')->paginate(10);

        return view('pages.user.payrolls.index', compact('payrolls'));
    }

    
    public function show($id)
    {
        $payroll = Payrool::where('user_id', Auth::id())->findOrFail($id);

        return view('pages.user.payrolls.show', compact('payroll'));
    }
}
