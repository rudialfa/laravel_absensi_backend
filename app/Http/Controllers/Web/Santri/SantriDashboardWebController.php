<?php

namespace App\Http\Controllers\Web\Santri;
use App\Http\Controllers\Controller; 
use App\Services\SantriApiService;
use Illuminate\Http\Request;


class SantriDashboardWebController extends Controller{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index()
    {
        $res       = $this->api->get('/santri/dashboard');
        $dashboard = $res->successful() ? $res->json('data', []) : [];
 
        // Jadwal sholat hari ini (endpoint terpisah)
        $prayerRes = $this->api->get('/prayers/today');
        $prayer    = $prayerRes->successful() ? $prayerRes->json('data', []) : [];
 
        return view('pages.santri.dashboard', compact('dashboard', 'prayer'));
    }
}
