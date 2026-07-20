<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Carbon;

class SuperAdminDashboardController extends Controller
{
    public function index()
    {
        $totalTenants   = Company::count();
        $activeTenants  = Company::where('is_active', true)->count();
        $suspended      = Company::where('is_active', false)->count();

        $activeSubs   = CompanySubscription::whereIn('status', ['trial', 'active'])->count();
        $trialSubs    = CompanySubscription::where('status', 'trial')->count();
        $graceSubs    = CompanySubscription::where('status', 'grace')->count();
        $expiredSubs  = CompanySubscription::where('status', 'expired')->count();

        $pendingInvoices = SubscriptionInvoice::where('status', 'pending')->count();

        $revenueThisMonth = SubscriptionInvoice::where('status', 'paid')
            ->whereMonth('paid_at', Carbon::now()->month)
            ->whereYear('paid_at', Carbon::now()->year)
            ->sum('total_amount');

        $latestInvoices = SubscriptionInvoice::with(['company', 'plan'])
            ->latest('issued_at')
            ->limit(8)
            ->get();

        $expiringSoon = CompanySubscription::with(['company', 'plan'])
            ->whereIn('status', ['active', 'trial', 'grace'])
            ->where('expires_at', '<=', Carbon::now()->addDays(3))
            ->orderBy('expires_at')
            ->limit(8)
            ->get();

        return view('pages.superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'suspended',
            'activeSubs',
            'trialSubs',
            'graceSubs',
            'expiredSubs',
            'pendingInvoices',
            'revenueThisMonth',
            'latestInvoices',
            'expiringSoon'
        ));
    }
}
