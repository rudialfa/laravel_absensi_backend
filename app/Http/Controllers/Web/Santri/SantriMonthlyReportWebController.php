<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriMonthlyReportWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $year       = $request->get('year', now()->year);
        $listRes    = $this->api->get('/santri/monthly-reports', ['year' => $year, 'per_page' => 12]);
        $summaryRes = $this->api->get('/santri/monthly-reports/summary', ['year' => $year]);
        $reports    = $listRes->successful()    ? $listRes->json('data', []) : [];
        $summary    = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
        return view('pages.santri.monthly-report.index', compact('reports', 'summary', 'year'));
    }
 
    public function create() { return view('pages.santri.monthly-report.create'); }
 
    public function store(Request $request)
    {
        $request->validate(['month'=>'required|integer|between:1,12','year'=>'required|integer','target'=>'required|string','achievement'=>'required|string','problem'=>'required|string','solution'=>'required|string','attachment'=>'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
 
        $res = $this->api->postMultipart('/santri/monthly-reports', $request->only('month','year','target','achievement','problem','solution'), ['attachment' => $request->file('attachment')]);
 
        if ($res->successful()) { SantriApiService::flashSuccess('Laporan berhasil dibuat.'); return redirect()->route('pages.santri.monthly-report.index'); }
        SantriApiService::flashError($res); return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res    = $this->api->get("/santri/monthly-reports/{$id}");
        $report = $res->successful() ? $res->json('data') : null;
        abort_if(!$report, 404);
        return view('pages.santri.monthly-report.show', compact('report'));
    }
 
    public function edit(int $id)
    {
        $res    = $this->api->get("/santri/monthly-reports/{$id}");
        $report = $res->successful() ? $res->json('data') : null;
        abort_if(!$report, 404);
        return view('pages.santri.monthly-report.edit', compact('report'));
    }
 
    public function update(Request $request, int $id)
    {
        $res = $this->api->postMultipart("/santri/monthly-reports/{$id}", $request->only('target','achievement','problem','solution'), ['attachment' => $request->file('attachment')]);
        $res->successful() ? SantriApiService::flashSuccess('Laporan diupdate.') : SantriApiService::flashError($res);
        return redirect()->route('pages.santri.monthly-report.show', $id);
    }
 
    public function submit(int $id)
    {
        $res = $this->api->patch("/santri/monthly-reports/{$id}/submit");
        $res->successful() ? SantriApiService::flashSuccess('Laporan disubmit ke ustadz.') : SantriApiService::flashError($res);
        return redirect()->route('pages.santri.monthly-report.show', $id);
    }
 
    public function destroy(int $id)
    {
        $res = $this->api->delete("/santri/monthly-reports/{$id}");
        $res->successful() ? SantriApiService::flashSuccess('Laporan dihapus.') : SantriApiService::flashError($res);
        return redirect()->route('pages.santri.monthly-report.index');
    }
}
