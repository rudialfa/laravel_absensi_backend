<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Http\Request;

class SuperAdminGlobalSearchController extends Controller
{
    /**
     * Pencarian cepat lintas semua data tenant: company, user, & invoice.
     * Dipakai superadmin buat cari cepat tanpa harus tahu tenant-nya di menu mana.
     */
    public function index(Request $request)
    {
        $keyword = trim((string) $request->input('q'));

        $companies = collect();
        $users = collect();
        $invoices = collect();

        if ($keyword !== '') {
            $companies = Company::where('name', 'like', "%{$keyword}%")
                ->orWhere('email', 'like', "%{$keyword}%")
                ->limit(15)
                ->get();

            $users = User::with('company')
                ->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                })
                ->limit(15)
                ->get();

            $invoices = SubscriptionInvoice::with('company')
                ->where('invoice_number', 'like', "%{$keyword}%")
                ->orWhereHas('company', fn($q) => $q->where('name', 'like', "%{$keyword}%"))
                ->limit(15)
                ->get();
        }

        return view('pages.superadmin.global-search.index', compact('keyword', 'companies', 'users', 'invoices'));
    }
}
