<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeHolidayWebController extends Controller
{
protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/company/employee/holidays', $request->only('type', 'from', 'to', 'page'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.holiday.index', compact('data'));
    }
 
    public function show(int $id)
    {
        $res     = $this->api->get("/company/employee/holidays/{$id}");
        $holiday = $res->successful() ? $res->json('data') : null;
        abort_if(!$holiday, 404);
        return view('pages.employee.holiday.show', compact('holiday'));
    }
}
