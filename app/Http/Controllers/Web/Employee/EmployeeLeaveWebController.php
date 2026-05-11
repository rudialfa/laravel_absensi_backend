<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeLeaveWebController extends Controller
{
protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/company/employee/leaves', $request->only('status', 'from', 'to', 'page'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.leave.index', compact('data'));
    }
 
    public function create()
    {
        return view('pages.employee.leave.create');
    }
 
    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'type'       => 'nullable|in:annual,sick,maternity,important,other',
            'reason'     => 'nullable|string',
        ]);
 
        $res = $this->api->post('/company/employee/leaves', $request->only('start_date', 'end_date', 'type', 'reason'));
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Cuti berhasil diajukan.');
            return redirect()->route('pages.employee.leave.index');
        }
 
        EmployeeApiService::flashError($res);
        return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res   = $this->api->get("/company/employee/leaves/{$id}");
        $leave = $res->successful() ? $res->json('data') : null;
        abort_if(!$leave, 404);
        return view('pages.employee.leave.show', compact('leave'));
    }
 
    public function cancel(int $id)
    {
        $res = $this->api->post("/company/employee/leaves/{$id}/cancel");
        $res->successful()
            ? EmployeeApiService::flashSuccess('Pengajuan cuti berhasil dibatalkan.')
            : EmployeeApiService::flashError($res);
        return redirect()->route('pages.employee.leave.index');
    }
}