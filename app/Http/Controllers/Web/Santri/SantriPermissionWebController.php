<?php

namespace App\Http\Controllers\Web\Santri;

use App\Services\SantriApiService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 


class SantriPermissionWebController extends Controller
{
    protected SantriApiService $api;
    public function __construct() { $this->api = new SantriApiService(); }
 
    public function index()
    {
        $res  = $this->api->get('/santri/permissions');
        $data = $res->successful() ? $res->json('data', []) : [];
        return view('pages.santri.permission.index', compact('data'));
    }
 
    public function create() { return view('pages.santri.permission.create'); }
 
    public function store(Request $request)
    {
        $request->validate([
            'date_permission' => 'required|date',
            'reason'          => 'required|string|max:500',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);
 
        $res = $this->api->postMultipart(
            '/santri/permissions',
            $request->only('date_permission', 'reason'),
            ['image' => $request->file('image')]
        );
 
        if ($res->successful()) {
            SantriApiService::flashSuccess('Izin berhasil diajukan.');
            return redirect()->route('pages.santri.permission.index');
        }
        SantriApiService::flashError($res);
        return back()->withInput();
    }
 
    public function show(int $id)
    {
        $res  = $this->api->get("/santri/permissions/{$id}");
        $perm = $res->successful() ? $res->json('data') : null;
        abort_if(!$perm, 404);
        return view('pages.santri.permission.show', compact('perm'));
    }
 
    public function cancel(int $id)
    {
        $res = $this->api->post("/santri/permissions/{$id}/cancel");
        $res->successful()
            ? SantriApiService::flashSuccess('Izin berhasil dibatalkan.')
            : SantriApiService::flashError($res);
        return redirect()->route('pages.santri.permission.index');
    }
}
