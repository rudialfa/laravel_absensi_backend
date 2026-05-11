<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriMutabaahWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index(Request $request)
    {
        $res     = $this->api->get('/santri/mutabaah', $request->only('kitab','jilid','sesi','bulan','tahun','per_page'));
        $records = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.mutabaah.index', compact('records'));
    }
 
    public function progress()
    {
        $res  = $this->api->get('/santri/mutabaah/progress');
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.mutabaah.progress', compact('data'));
    }
 
    public function show(int $id)
    {
        $res    = $this->api->get("/santri/mutabaah/{$id}");
        $record = $res->successful() ? $res->json('data') : null;
        abort_if(!$record, 404);
        return view('pages.santri.mutabaah.show', compact('record'));
    }
}
