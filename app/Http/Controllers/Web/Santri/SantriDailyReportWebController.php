<?php

namespace App\Http\Controllers\Web\Santri;
use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SantriDailyReportWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $month  = $request->get('month', now()->month);
        $year   = $request->get('year', now()->year);
 
        $listRes    = $this->api->get('/santri/daily-reports', ['month' => $month, 'year' => $year, 'per_page' => 31]);
        $summaryRes = $this->api->get('/santri/daily-reports/summary', compact('month', 'year'));
 
        $reports = $listRes->successful()    ? $listRes->json('data', []) : [];
        $summary = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
 
        return view('pages.santri.daily-report.index', compact('reports', 'summary', 'month', 'year'));
    }
 
    public function today()
    {
        $res   = $this->api->get('/santri/daily-reports/today');
        $today = $res->successful() ? $res->json() : [];
        return view('pages.santri.daily-report.today', compact('today'));
    }
 
    public function store(Request $request)
    {
        $request->validate(['target' => 'required|string', 'attachment' => 'nullable|image|mimes:jpg,jpeg,png|max:2048']);
 
        $res = $this->api->postMultipart('/santri/daily-reports', ['target' => $request->target], ['attachment' => $request->file('attachment')]);
 
        $res->successful()
            ? SantriApiService::flashSuccess('Target pagi berhasil disubmit!')
            : SantriApiService::flashError($res);
 
        return redirect()->route('pages.santri.daily-report.today');
    }
 
    public function show(int $id)
    {
        $res    = $this->api->get("/santri/daily-reports/{$id}");
        $report = $res->successful() ? $res->json('data') : null;
        abort_if(!$report, 404);
        return view('pages.santri.daily-report.show', compact('report'));
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
            "/santri/daily-reports/{$id}",
            $request->only('achievement', 'is_achieved', 'reason_not_achieved'),
            ['attachment' => $request->file('attachment')]
        );
 
        $res->successful()
            ? SantriApiService::flashSuccess('Pencapaian sore berhasil disubmit!')
            : SantriApiService::flashError($res);
 
        return redirect()->route('pages.santri.daily-report.today');
    }
}
