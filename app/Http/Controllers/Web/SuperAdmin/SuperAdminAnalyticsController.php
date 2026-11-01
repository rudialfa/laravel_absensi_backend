<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminAnalyticsController extends Controller
{
    public function index()
    {
        $now = Carbon::now();
        $startThisMonth = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endLastMonth = $now->copy()->subMonth()->endOfMonth();

        // ── Revenue bulan ini vs bulan lalu ──────────────────────────
        $revenueThisMonth = SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$startThisMonth, $now])
            ->sum('total_amount');

        $revenueLastMonth = SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$startLastMonth, $endLastMonth])
            ->sum('total_amount');

        $revenueGrowthPercent = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        // ── Tenant per tipe & status ──────────────────────────────────
        $tenantsByType = Company::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $activeTenants = Company::where('is_active', true)->count();
        $suspendedTenants = Company::where('is_active', false)->count();

        // ── Subscription per status ───────────────────────────────────
        $subscriptionsByStatus = CompanySubscription::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // ── Revenue per plan (paid invoices) ──────────────────────────
        $revenueByPlan = SubscriptionInvoice::join('subscription_plans', 'subscription_plans.id', '=', 'subscription_invoices.plan_id')
            ->where('subscription_invoices.status', 'paid')
            ->select('subscription_plans.name as plan_name', DB::raw('sum(subscription_invoices.total_amount) as total'))
            ->groupBy('subscription_plans.name')
            ->orderByDesc('total')
            ->get();

        // ── Invoice pending & overdue ─────────────────────────────────
        $pendingInvoices = SubscriptionInvoice::where('status', 'pending')->count();
        $overdueInvoices = SubscriptionInvoice::where('status', 'pending')
            ->where('due_at', '<', $now)
            ->count();

        // ── Tren tenant baru 6 bulan terakhir ─────────────────────────
        $newTenantsTrend = Company::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('count(*) as total')
        )
            ->where('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('total', 'month');

        return view('pages.superadmin.analytics.index', compact(
            'revenueThisMonth',
            'revenueLastMonth',
            'revenueGrowthPercent',
            'tenantsByType',
            'activeTenants',
            'suspendedTenants',
            'subscriptionsByStatus',
            'revenueByPlan',
            'pendingInvoices',
            'overdueInvoices',
            'newTenantsTrend'
        ));
    }
}
