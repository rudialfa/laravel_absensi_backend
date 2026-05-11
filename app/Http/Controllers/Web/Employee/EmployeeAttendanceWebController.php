<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeeAttendanceWebController extends Controller
{
 protected EmployeeApiService $api;
 
    public function __construct()
    {
        $this->api = new EmployeeApiService();
    }
 
    /**
     * Halaman utama absensi — status hari ini + riwayat + statistik bulanan.
     */
    public function index(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
 
        // Paralel call ke 3 endpoint sekaligus
        $statusRes  = $this->api->get('/company/employee/attendances/is-checkin');
        $historyRes = $this->api->get('/company/employee/attendances/history', ['limit' => 30]);
        $summaryRes = $this->api->get('/company/employee/stats/summary', [
            'month' => $month,
            'year'  => $year,
        ]);
 
        $status  = $statusRes->successful()  ? $statusRes->json()  : [];
        $history = $historyRes->successful() ? $historyRes->json('data', []) : [];
        $summary = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
 
        return view('pages.employee.attendance.index', compact('status', 'history', 'summary', 'month', 'year'));
    }
 
    /**
     * Proses check-in (form POST dari halaman web — koordinat dikirim via hidden input JS).
     */
    public function checkIn(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
 
        $res = $this->api->post('/company/employee/attendances/check-in', [
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess($res->json('message', 'Check-in berhasil!'));
        } else {
            EmployeeApiService::flashError($res);
        }
 
        return redirect()->route('pages.employee.attendance.index');
    }
 
    /**
     * Proses check-out.
     */
    public function checkOut(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);
 
        $res = $this->api->post('/company/employee/attendances/check-out', [
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);
 
        if ($res->successful()) {
            EmployeeApiService::flashSuccess($res->json('message', 'Check-out berhasil!'));
        } else {
            EmployeeApiService::flashError($res);
        }
 
        return redirect()->route('pages.employee.attendance.index');
    }
}