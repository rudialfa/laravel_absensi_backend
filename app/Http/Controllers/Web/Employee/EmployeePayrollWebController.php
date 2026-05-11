<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeePayrollWebController extends Controller
{
protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/company/employee/payrolls', $request->only('status', 'from', 'to', 'page'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.payroll.index', compact('data'));
    }
 
    public function show(int $id)
    {
        $res     = $this->api->get("/company/employee/payrolls/{$id}");
        $payroll = $res->successful() ? $res->json('data') : null;
        abort_if(!$payroll, 404);
        return view('pages.employee.payroll.show', compact('payroll'));
    }
}
 
 