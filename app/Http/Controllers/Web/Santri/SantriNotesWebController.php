<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriNotesWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $summaryRes = $this->api->get('/santri/notes/summary');
        $listRes    = $this->api->get('/santri/notes', $request->only('type','is_read','search','page'));
        $summary    = $summaryRes->successful() ? $summaryRes->json('data', []) : [];
        $notes      = $listRes->successful()    ? $listRes->json('data', []) : [];
        return view('pages.santri.notes.index', compact('summary', 'notes'));
    }
 
    public function show(int $id)
    {
        $res  = $this->api->get("/santri/notes/{$id}");
        $note = $res->successful() ? $res->json('data') : null;
        abort_if(!$note, 404);
        return view('pages.santri.notes.show', compact('note'));
    }
 
    public function markRead(int $id)
    {
        $this->api->patch("/santri/notes/{$id}/read");
        return redirect()->route('pages.santri.notes.index');
    }
}
 
