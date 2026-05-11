<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 

class SantriQuranWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index()
    {
        $res   = $this->api->get('/santri/quran/surah');
        $surah = $res->successful() ? $res->json('data.surah', []) : [];
        return view('pages.santri.quran.index', compact('surah'));
    }
 
    public function progress()
    {
        $res  = $this->api->get('/santri/quran/progress');
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.quran.progress', compact('data'));
    }
 
    public function surah(int $number, Request $request)
    {
        $withAudio = $request->boolean('audio', false);
        $res       = $this->api->get("/santri/quran/surah/{$number}", ['with_audio' => $withAudio ? 1 : 0]);
        $data      = $res->successful() ? $res->json('data') : null;
        abort_if(!$data, 404);
        return view('pages.santri.quran.surah', compact('data', 'number'));
    }
 
    public function halaman(int $page)
    {
        $res  = $this->api->get("/santri/quran/halaman/{$page}");
        $data = $res->successful() ? $res->json('data') : null;
        abort_if(!$data, 404);
        return view('pages.santri.quran.halaman', compact('data', 'page'));
    }
 
    public function sesiDetail(int $id)
    {
        $res  = $this->api->get("/santri/quran/sesi/{$id}");
        $sesi = $res->successful() ? $res->json('data') : null;
        abort_if(!$sesi, 404);
        return view('pages.santri.quran.sesi', compact('sesi'));
    }
}