<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeOvertimeWebController extends Controller
{
 protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/company/employee/overtimes', $request->only('status', 'from', 'to', 'page'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.overtime.index', compact('data'));
    }
 
    public function create()
    {
        return view('pages.employee.overtime.create');
    }
 
    public function store(Request $request)
    {
        $request->validate([
            'date'           => 'required|date',
            'start_time'     => 'required|date_format:H:i',
            'end_time'       => 'required|date_format:H:i',
            'reason'         => 'nullable|string',
            'evidence_image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
 
        $res = $this->api->postMultipart(
            '/company/employee/overtimes',
            $request->only('date', 'start_time', 'end_time', 'reason'),
            ['evidence_image' => $request->file('evidence_image')]
        );
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Pengajuan lembur berhasil dikirim.');
            return redirect()->route('pages.employee.overtime.index');
        }
 
        EmployeeApiService::flashError($res);
        return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res      = $this->api->get("/company/employee/overtimes/{$id}");
        $overtime = $res->successful() ? $res->json('data') : null;
        abort_if(!$overtime, 404);
        return view('pages.employee.overtime.show', compact('overtime'));
    }
 
    public function cancel(int $id)
    {
        $res = $this->api->post("/company/employee/overtimes/{$id}/cancel");
        $res->successful()
            ? EmployeeApiService::flashSuccess('Pengajuan lembur berhasil dibatalkan.')
            : EmployeeApiService::flashError($res);
        return redirect()->route('pages.employee.overtime.index');
    }
}
