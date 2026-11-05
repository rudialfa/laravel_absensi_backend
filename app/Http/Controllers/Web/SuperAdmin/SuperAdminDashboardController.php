<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminDashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        // ── Tenant ───────────────────────────────────────────────────
        $totalTenants       = Company::count();
        $activeTenants      = Company::where('is_active', true)->count();
        $suspended          = $totalTenants - $activeTenants;
        $newTenantsMonth    = Company::where('created_at', '>=', $now->copy()->startOfMonth())->count();
        $tenantsWithoutSubs = Company::doesntHave('subscriptions')->count();

        $tenantsByType = Company::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        // ── Pengguna (semua tenant, tanpa akun superadmin) ───────────
        $userQuery   = User::where('role', '!=', 'superadmin');
        $totalUsers  = (clone $userQuery)->count();
        $activeUsers = (clone $userQuery)->where('is_active', true)->count();

        $usersByRole = (clone $userQuery)
            ->select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->orderByDesc('total')
            ->pluck('total', 'role');

        // ── Langganan ────────────────────────────────────────────────
        $subsByStatus = CompanySubscription::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $trialSubs   = (int) ($subsByStatus['trial'] ?? 0);
        $paidSubs    = (int) ($subsByStatus['active'] ?? 0);
        $graceSubs   = (int) ($subsByStatus['grace'] ?? 0);
        $expiredSubs = (int) ($subsByStatus['expired'] ?? 0) + (int) ($subsByStatus['cancelled'] ?? 0);
        $activeSubs  = $trialSubs + $paidSubs;

        // ── Invoice & pendapatan ────────────────────────────────────
        $pendingInvoices = SubscriptionInvoice::where('status', 'pending')->count();
        $overdueInvoices = SubscriptionInvoice::where('status', 'pending')
            ->where('due_at', '<', $now)
            ->count();

        $revenueThisMonth = SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$now->copy()->startOfMonth(), $now])
            ->sum('total_amount');

        $revenueLastMonth = SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ])
            ->sum('total_amount');

        $revenueGrowth = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : null;

        // ── Tiket bantuan ───────────────────────────────────────────
        $openTickets       = SupportTicket::where('status', 'open')->count();
        $inProgressTickets = SupportTicket::where('status', 'in_progress')->count();

        // ── Daftar ──────────────────────────────────────────────────
        $latestInvoices = SubscriptionInvoice::with(['company', 'plan'])
            ->latest('issued_at')
            ->limit(6)
            ->get();

        // Langganan trial/aktif yang habis dalam 7 hari ke depan
        $expiringSoon = CompanySubscription::with(['company', 'plan'])
            ->whereIn('status', ['active', 'trial'])
            ->whereBetween('expires_at', [$now, $now->copy()->addDays(7)])
            ->orderBy('expires_at')
            ->limit(6)
            ->get();

        // Sudah lewat masa aktif, sedang dalam masa toleransi
        $graceList = CompanySubscription::with(['company', 'plan'])
            ->where('status', 'grace')
            ->orderBy('expires_at')
            ->limit(6)
            ->get();

        $latestTenants = Company::withCount('users')
            ->latest()
            ->limit(5)
            ->get();

        $latestTickets = SupportTicket::with(['company', 'user'])
            ->whereIn('status', ['open', 'in_progress'])
            ->latest()
            ->limit(5)
            ->get();

        return view('pages.superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'suspended',
            'newTenantsMonth',
            'tenantsWithoutSubs',
            'tenantsByType',
            'totalUsers',
            'activeUsers',
            'usersByRole',
            'activeSubs',
            'trialSubs',
            'paidSubs',
            'graceSubs',
            'expiredSubs',
            'pendingInvoices',
            'overdueInvoices',
            'revenueThisMonth',
            'revenueLastMonth',
            'revenueGrowth',
            'openTickets',
            'inProgressTickets',
            'latestInvoices',
            'expiringSoon',
            'graceList',
            'latestTenants',
            'latestTickets'
        ));
    }
}
