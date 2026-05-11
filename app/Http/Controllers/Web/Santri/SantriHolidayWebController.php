<?php

namespace App\Http\Controllers\Web\Santri;
use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 

class SantriHolidayWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $res  = $this->api->get('/santri/holidays', $request->only('type','from','to','page'));
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.holiday.index', compact('data'));
    }
 
    public function show(int $id)
    {
        $res     = $this->api->get("/santri/holidays/{$id}");
        $holiday = $res->successful() ? $res->json('data') : null;
        abort_if(!$holiday, 404);
        return view('pages.santri.holiday.show', compact('holiday'));
    }
}