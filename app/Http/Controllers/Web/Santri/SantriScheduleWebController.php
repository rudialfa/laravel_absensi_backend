<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriScheduleWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $res       = $this->api->get('/santri/schedules', $request->only('scope','status','type','start_date','end_date','page'));
        $schedules = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.schedule.index', compact('schedules'));
    }
 
    public function today()
    {
        $res  = $this->api->get('/santri/schedules/today');
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.schedule.today', compact('data'));
    }
 
    public function invitations(Request $request)
    {
        $res   = $this->api->get('/santri/schedules/invitations', $request->only('status','page'));
        $items = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.schedule.invitations', compact('items'));
    }
 
    public function show(int $id)
    {
        $res      = $this->api->get("/santri/schedules/{$id}");
        $schedule = $res->successful() ? $res->json('data') : null;
        abort_if(!$schedule, 404);
        return view('pages.santri.schedule.show', compact('schedule'));
    }
 
    public function respond(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:accepted,declined', 'note' => 'nullable|string|max:500']);
 
        $res = $this->api->post("/santri/schedules/{$id}/respond", $request->only('status','note'));
 
        $res->successful()
            ? SantriApiService::flashSuccess($request->status === 'accepted' ? 'Undangan diterima.' : 'Undangan ditolak.')
            : SantriApiService::flashError($res);
 
        return redirect()->route('pages.santri.schedule.index');
    }
}
