<?php

namespace App\Http\Controllers\Web\Santri;
use App\Http\Controllers\Controller; 
use App\Services\SantriApiService;
use Illuminate\Http\Request;

class SantriAttendanceWebController extends Controller{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
 
        $statusRes  = $this->api->get('/santri/attendances/is-checkin');
        $historyRes = $this->api->get('/santri/attendances/history', ['limit' => 30]);
        $summaryRes = $this->api->get('/santri/attendances/summary', compact('month', 'year'));
 
        $status  = $statusRes->successful()  ? $statusRes->json()  : [];
        $history = $historyRes->successful() ? $historyRes->json('data', []) : [];
        $summary = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
 
        return view('pages.santri.attendance.index', compact('status', 'history', 'summary', 'month', 'year'));
    }
 
    public function checkIn(Request $request)
    {
        $request->validate(['latitude' => 'required|numeric', 'longitude' => 'required|numeric']);
 
        $res = $this->api->post('/santri/attendances/check-in', $request->only('latitude', 'longitude'));
 
        $res->successful()
            ? SantriApiService::flashSuccess($res->json('message', 'Check-in berhasil!'))
            : SantriApiService::flashError($res);
 
        return redirect()->route('pages.santri.attendance.index');
    }
 
    public function checkOut(Request $request)
    {
        $request->validate(['latitude' => 'required|numeric', 'longitude' => 'required|numeric']);
 
        $res = $this->api->post('/santri/attendances/check-out', $request->only('latitude', 'longitude'));
 
        $res->successful()
            ? SantriApiService::flashSuccess($res->json('message', 'Check-out berhasil!'))
            : SantriApiService::flashError($res);
 
        return redirect()->route('pages.santri.attendance.index');
    }
}
