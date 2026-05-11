<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriPerformanceWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $month  = $request->get('month', now()->month);
        $year   = $request->get('year', now()->year);
        $res    = $this->api->get('/santri/performance', compact('month', 'year'));
        $scores = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.performance.index', compact('scores', 'month', 'year'));
    }
 
    public function leaderboard(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
        $res   = $this->api->get('/santri/performance/leaderboard', compact('month', 'year'));
        $data  = $res->successful() ? $res->json() : [];
        return view('pages.santri.performance.leaderboard', compact('data', 'month', 'year'));
    }
 
    public function show(int $id)
    {
        $res   = $this->api->get("/santri/performance/{$id}");
        $score = $res->successful() ? $res->json('data') : null;
        abort_if(!$score, 404);
        return view('pages.santri.performance.show', compact('score'));
    }
}
 
