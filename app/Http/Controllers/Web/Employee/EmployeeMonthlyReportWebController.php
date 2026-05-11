<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeMonthlyReportWebController extends Controller
{
  protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $year = $request->get('year', now()->year);
 
        $listRes    = $this->api->get('/company/employee/monthly-reports', ['year' => $year, 'per_page' => 12]);
        $summaryRes = $this->api->get('/company/employee/monthly-reports/summary', ['year' => $year]);
 
        $reports = $listRes->successful()    ? $listRes->json('data', []) : [];
        $summary = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
 
        return view('pages.employee.monthly-report.index', compact('reports', 'summary', 'year'));
    }
 
    public function create()
    {
        return view('pages.employee.monthly-report.create');
    }
 
    public function store(Request $request)
    {
        $request->validate([
            'month'       => 'required|integer|between:1,12',
            'year'        => 'required|integer',
            'target'      => 'required|string',
            'achievement' => 'required|string',
            'problem'     => 'required|string',
            'solution'    => 'required|string',
            'attachment'  => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
 
        $res = $this->api->postMultipart(
            '/company/employee/monthly-reports',
            $request->only('month', 'year', 'target', 'achievement', 'problem', 'solution'),
            ['attachment' => $request->file('attachment')]
        );
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Laporan bulanan berhasil dibuat sebagai draft.');
            return redirect()->route('pages.employee.monthly-report.index');
        }
 
        EmployeeApiService::flashError($res);
        return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res    = $this->api->get("/company/employee/monthly-reports/{$id}");
        $report = $res->successful() ? $res->json('data') : null;
        abort_if(!$report, 404);
        return view('pages.employee.monthly-report.show', compact('report'));
    }
 
    public function edit(int $id)
    {
        $res    = $this->api->get("/company/employee/monthly-reports/{$id}");
        $report = $res->successful() ? $res->json('data') : null;
        abort_if(!$report, 404);
        return view('pages.employee.monthly-report.edit', compact('report'));
    }
 
    public function update(Request $request, int $id)
    {
        $res = $this->api->postMultipart(
            "/company/employee/monthly-reports/{$id}",
            $request->only('target', 'achievement', 'problem', 'solution'),
            ['attachment' => $request->file('attachment')]
        );
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess('Laporan berhasil diupdate.');
            return redirect()->route('pages.employee.monthly-report.show', $id);
        }
 
        EmployeeApiService::flashError($res);
        return back()->withInput();
    }
 
    public function submit(int $id)
    {
        $res = $this->api->patch("/company/employee/monthly-reports/{$id}/submit");
        $res->successful()
            ? EmployeeApiService::flashSuccess('Laporan berhasil disubmit ke HR.')
            : EmployeeApiService::flashError($res);
        return redirect()->route('pages.employee.monthly-report.show', $id);
    }
 
    public function destroy(int $id)
    {
        $res = $this->api->delete("/company/employee/monthly-reports/{$id}");
        $res->successful()
            ? EmployeeApiService::flashSuccess('Laporan berhasil dihapus.')
            : EmployeeApiService::flashError($res);
        return redirect()->route('pages.pages.employee.monthly-report.index');
    }
}
 
