<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeNotesWebController extends Controller
{
 protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }

    public function index(Request $request)
    {
        $summaryRes = $this->api->get('/company/employee/notes/summary');
        $listRes    = $this->api->get('/company/employee/notes', $request->only('type', 'is_read', 'search', 'page'));

        $summary = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
        $notes   = $listRes->successful()    ? $listRes->json('data', []) : [];

        return view('pages.employee.notes.index', compact('summary', 'notes'));
    }

    public function show(int $id)
    {
        $res  = $this->api->get("/company/employee/notes/{$id}");
        $note = $res->successful() ? $res->json('data') : null;
        abort_if(!$note, 404);
        return view('pages.employee.notes.show', compact('note'));
    }

    public function markRead(int $id)
    {
        $this->api->patch("/company/employee/notes/{$id}/read");
        return redirect()->route('pages.employee.notes.index');
    }
}
