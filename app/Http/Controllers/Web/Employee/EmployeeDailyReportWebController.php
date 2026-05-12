<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeDailyReportWebController extends Controller
{
protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }

    public function index(Request $request)
    {
        $month  = $request->get('month', now()->month);
        $year   = $request->get('year',  now()->year);

        $listRes    = $this->api->get('/company/employee/daily-reports', ['month' => $month, 'year' => $year, 'per_page' => 31]);
        $summaryRes = $this->api->get('/company/employee/daily-reports/summary', ['month' => $month, 'year' => $year]);

        $reports = $listRes->successful()    ? $listRes->json('data', []) : [];
        $summary = $summaryRes->successful() ? $summaryRes->json('data', []) : [];

        return view('pages.employee.daily-report.index', compact('reports', 'summary', 'month', 'year'));
    }

    public function today()
    {
        $res    = $this->api->get('/company/employee/daily-reports/today');
        $today  = $res->successful() ? $res->json() : [];
        return view('pages.employee.daily-report.today', compact('today'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'target'     => 'required|string',
            'attachment' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $res = $this->api->postMultipart(
            '/company/employee/daily-reports',
            ['target' => $request->target],
            ['attachment' => $request->file('attachment')]
        );

        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Target pagi berhasil disubmit!');
        } else {
            EmployeeApiService::flashError($res);
        }

        return redirect()->route('employee.daily-report.today');
    }

    public function show(int $id)
    {
        $res    = $this->api->get("/company/employee/daily-reports/{$id}");
        $report = $res->successful() ? $res->json('data') : null;
        abort_if(!$report, 404);
        return view('pages.employee.daily-report.show', compact('report'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'achievement'         => 'required|string',
            'is_achieved'         => 'required|in:1,0,true,false',
            'reason_not_achieved' => 'nullable|string',
            'attachment'          => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $res = $this->api->postMultipart(
            "/company/employee/daily-reports/{$id}",
            $request->only('achievement', 'is_achieved', 'reason_not_achieved'),
            ['attachment' => $request->file('attachment')]
        );

        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Pencapaian sore berhasil disubmit!');
        } else {
            EmployeeApiService::flashError($res);
        }

        return redirect()->route('employee.daily-report.today');
    }

    public function export(Request $request)
    {
        // Forward ke PDF download endpoint API
        $token = session('api_token', '');
        $query = http_build_query($request->only('month', 'year', 'start', 'end', 'is_achieved'));
        $url   = config('app.url') . "/api/company/employee/daily-reports/export?{$query}";

        return redirect($url . '&token=' . $token);
    }
}
