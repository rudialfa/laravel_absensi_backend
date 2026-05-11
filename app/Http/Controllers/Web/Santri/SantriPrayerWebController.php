<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriPrayerWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function today()
    {
        $res    = $this->api->get('/prayers/today');
        $prayer = $res->successful() ? $res->json('data', []) : [];
 
        // Waktu sholat berikutnya
        $nextRes = $this->api->get('/prayers/next');
        $next    = $nextRes->successful() ? $nextRes->json('data', []) : [];
 
        return view('pages.santri.prayer.today', compact('prayer', 'next'));
    }
 
    public function monthly(Request $request)
    {
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);
 
        $res  = $this->api->get('/prayers/monthly', compact('month', 'year'));
        $data = $res->successful() ? $res->json('data', []) : [];
 
        return view('pages.santri.prayer.monthly', compact('data', 'month', 'year'));
    }
 
    public function byDate(string $date)
    {
        $res    = $this->api->get("/prayers/{$date}");
        $prayer = $res->successful() ? $res->json('data') : null;
        abort_if(!$prayer, 404);
        return view('pages.santri.prayer.show', compact('prayer', 'date'));
    }
}
 
