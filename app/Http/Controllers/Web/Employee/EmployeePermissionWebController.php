<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeePermissionWebController extends Controller
{
  protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/company/employee/permissions', $request->only('page', 'per_page'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.permission.index', compact('data'));
    }
 
    public function create()
    {
        return view('pages.employee.permission.create');
    }
 
    public function store(Request $request)
    {
        $request->validate([
            'date_permission' => 'required|date',
            'reason'          => 'required|string|max:500',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
 
        $res = $this->api->postMultipart(
            '/company/employee/permissions',
            $request->only('date_permission', 'reason'),
            ['image' => $request->file('image')]
        );
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Izin berhasil diajukan.');
            return redirect()->route('pages.employee.permission.index');
        }
 
        EmployeeApiService::flashError($res);
        return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res  = $this->api->get("/company/employee/permissions/{$id}");
        $perm = $res->successful() ? $res->json('data') : null;
        abort_if(!$perm, 404);
        return view('pages.employee.permission.show', compact('perm'));
    }
 
    public function cancel(int $id)
    {
        $res = $this->api->post("/company/employee/permissions/{$id}/cancel");
        $res->successful()
            ? EmployeeApiService::flashSuccess('Izin berhasil dibatalkan.')
            : EmployeeApiService::flashError($res);
        return redirect()->route('pages.employee.permission.index');
    }
}