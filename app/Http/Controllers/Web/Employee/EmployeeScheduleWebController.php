<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeScheduleWebController extends Controller
{
 protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $res       = $this->api->get('/company/employee/schedules', $request->only('scope', 'status', 'type', 'start_date', 'end_date', 'page'));
        $schedules = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.schedule.index', compact('schedules'));
    }
 
    public function invitations(Request $request)
    {
        $res   = $this->api->get('/company/employee/schedules/invitations', $request->only('status', 'page'));
        $items = $res->successful() ? $res->json('data', []) : [];
        return view('pages.employee.schedule.invitations', compact('items'));
    }
 
    public function show(int $id)
    {
        $res      = $this->api->get("/company/employee/schedules/{$id}");
        $schedule = $res->successful() ? $res->json('data') : null;
        abort_if(!$schedule, 404);
        return view('pages.employee.schedule.show', compact('schedule'));
    }
 
    public function respond(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:accepted,declined',
            'note'   => 'nullable|string|max:500',
        ]);
 
        $res = $this->api->post("/company/employee/schedules/{$id}/respond", $request->only('status', 'note'));
 
        $res->successful()
            ? EmployeeApiService::flashSuccess($request->status === 'accepted' ? 'Undangan diterima.' : 'Undangan ditolak.')
            : EmployeeApiService::flashError($res);
 
        return redirect()->route('pages.pages.employee.schedule.index');
    }
}
