<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\EmployeeApiService;
use Illuminate\Http\Request;

class EmployeePerformanceWebController extends Controller
{
 protected EmployeeApiService $api;
    public function __construct() { $this->api = new EmployeeApiService(); }
 
    public function index(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
 
        $res    = $this->api->get('/company/employee/performance-scores', compact('month', 'year'));
        $scores = $res->successful() ? $res->json('data', []) : [];
 
        return view('pages.employee.performance.index', compact('scores', 'month', 'year'));
    }
 
    public function leaderboard(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
 
        $res  = $this->api->get('/company/employee/performance-scores/leaderboard', compact('month', 'year'));
        $data = $res->successful() ? $res->json() : [];
 
        return view('pages.employee.performance.leaderboard', compact('data', 'month', 'year'));
    }
 
    public function show(int $id)
    {
        $res   = $this->api->get("/company/employee/performance-scores/{$id}");
        $score = $res->successful() ? $res->json('data') : null;
        abort_if(!$score, 404);
        return view('pages.employee.performance.show', compact('score'));
    }
}