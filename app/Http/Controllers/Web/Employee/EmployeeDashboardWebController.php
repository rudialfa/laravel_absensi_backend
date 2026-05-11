<?php

namespace App\Http\Controllers\Web\Employee;

use App\Http\Controllers\Controller;
use App\Services\SantriApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeDashboardWebController extends Controller
{
    protected SantriApiService $api;

    public function __construct()
    {
        $this->api = new SantriApiService();
    }

    public function index()
    {
        $user = Auth::user();

        // Ambil data dari API jika diperlukan, contoh:
        // $dashboard = $this->api->getDashboardData($user->id);

        $dashboard = [
            'user' => $user,
            // tambahkan data lain sesuai kebutuhan
        ];

        return view('pages.employee.dashboard', compact('dashboard'));
    }
}