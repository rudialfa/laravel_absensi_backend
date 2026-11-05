<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminAnalyticsController extends Controller
{
    /** Pilihan rentang periode (bulan) */
    private const RANGES = [3, 6, 12, 24];

    public function index(Request $request)
    {
        $months = (int) $request->input('months', 12);
        if (!in_array($months, self::RANGES, true)) {
            $months = 12;
        }

        $now         = Carbon::now();
        $periodStart = $now->copy()->subMonthsNoOverflow($months - 1)->startOfMonth();

        // Label bulan berurutan (dipakai semua grafik tren, bulan kosong = 0)
        $monthKeys   = [];
        $monthLabels = [];
        for ($i = 0; $i < $months; $i++) {
            $m             = $periodStart->copy()->addMonthsNoOverflow($i);
            $monthKeys[]   = $m->format('Y-m');
            $monthLabels[] = $m->translatedFormat('M Y');
        }

        $fill = fn($collection) => array_map(
            fn($key) => (float) ($collection[$key] ?? 0),
            $monthKeys
        );

        // ── 1. Pendapatan bulan ini vs bulan lalu ─────────────────────
        $startThisMonth = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endLastMonth   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $revenueThisMonth = (float) SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$startThisMonth, $now])
            ->sum('total_amount');

        $revenueLastMonth = (float) SubscriptionInvoice::where('status', 'paid')
            ->whereBetween('paid_at', [$startLastMonth, $endLastMonth])
            ->sum('total_amount');

        // null = bulan lalu 0, tidak bisa dihitung persentasenya
        $revenueGrowthPercent = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : null;

        // ── 2. Ringkasan periode terpilih ─────────────────────────────
        $paidInPeriod = SubscriptionInvoice::where('status', 'paid')
            ->where('paid_at', '>=', $periodStart);

        $revenuePeriod      = (float) (clone $paidInPeriod)->sum('total_amount');
        $discountPeriod     = (float) (clone $paidInPeriod)->sum('discount_amount');
        $paidInvoicesPeriod = (clone $paidInPeriod)->count();
        $payingTenants      = (clone $paidInPeriod)->distinct()->count('company_id');
        $avgPerInvoice      = $paidInvoicesPeriod > 0 ? $revenuePeriod / $paidInvoicesPeriod : 0;

        // ── 3. MRR (estimasi pendapatan bulanan dari langganan berbayar aktif)
        //      harga paket dinormalisasi ke 30 hari
        $mrr = (float) CompanySubscription::join('subscription_plans', 'subscription_plans.id', '=', 'company_subscriptions.plan_id')
            ->where('company_subscriptions.status', 'active')
            ->whereNull('company_subscriptions.deleted_at')
            ->where('subscription_plans.duration_days', '>', 0)
            ->sum(DB::raw('subscription_plans.price / subscription_plans.duration_days * 30'));

        // ── 4. Tenant ─────────────────────────────────────────────────
        $totalTenants     = Company::count();
        $activeTenants    = Company::where('is_active', true)->count();
        $suspendedTenants = $totalTenants - $activeTenants;

        $tenantsByType = Company::select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        // Konversi: dari tenant yang pernah berlangganan, berapa yang pernah bayar
        $everSubscribed = DB::table('company_subscriptions')->distinct()->count('company_id');
        $everPaid       = SubscriptionInvoice::where('status', 'paid')->distinct()->count('company_id');
        $conversionRate = $everSubscribed > 0 ? round($everPaid / $everSubscribed * 100, 1) : null;

        // ── 5. Langganan per status ───────────────────────────────────
        $subscriptionsByStatus = CompanySubscription::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // ── 6. Invoice ────────────────────────────────────────────────
        $pendingInvoices = SubscriptionInvoice::where('status', 'pending')->count();
        $overdueInvoices = SubscriptionInvoice::where('status', 'pending')
            ->where('due_at', '<', $now)
            ->count();

        $invoicesByStatus = SubscriptionInvoice::where('issued_at', '>=', $periodStart)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // ── 7. Tren per bulan ─────────────────────────────────────────
        $revenueTrend = SubscriptionInvoice::where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->select(DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as ym"), DB::raw('sum(total_amount) as total'))
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $paidCountTrend = SubscriptionInvoice::where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->select(DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as ym"), DB::raw('count(*) as total'))
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $newTenantsTrend = Company::where('created_at', '>=', $periodStart)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as ym"), DB::raw('count(*) as total'))
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $trend = [
            'labels'     => $monthLabels,
            'revenue'    => $fill($revenueTrend),
            'paidCount'  => $fill($paidCountTrend),
            'newTenants' => $fill($newTenantsTrend),
        ];

        // ── 8. Pendapatan per paket & per jenis lembaga (periode) ────
        $revenueByPlan = SubscriptionInvoice::join('subscription_plans', 'subscription_plans.id', '=', 'subscription_invoices.plan_id')
            ->where('subscription_invoices.status', 'paid')
            ->where('subscription_invoices.paid_at', '>=', $periodStart)
            ->select(
                'subscription_plans.name as plan_name',
                DB::raw('count(*) as invoices'),
                DB::raw('sum(subscription_invoices.total_amount) as total')
            )
            ->groupBy('subscription_plans.name')
            ->orderByDesc('total')
            ->get();

        $revenueByType = SubscriptionInvoice::join('companies', 'companies.id', '=', 'subscription_invoices.company_id')
            ->where('subscription_invoices.status', 'paid')
            ->where('subscription_invoices.paid_at', '>=', $periodStart)
            ->select('companies.type', DB::raw('sum(subscription_invoices.total_amount) as total'))
            ->groupBy('companies.type')
            ->orderByDesc('total')
            ->pluck('total', 'type');

        // ── 9. Tenant dengan pembayaran terbesar (periode) ───────────
        $topTenants = SubscriptionInvoice::join('companies', 'companies.id', '=', 'subscription_invoices.company_id')
            ->where('subscription_invoices.status', 'paid')
            ->where('subscription_invoices.paid_at', '>=', $periodStart)
            ->select(
                'companies.id',
                'companies.name',
                'companies.type',
                DB::raw('count(*) as invoices'),
                DB::raw('sum(subscription_invoices.total_amount) as total'),
                DB::raw('max(subscription_invoices.paid_at) as last_paid_at')
            )
            ->groupBy('companies.id', 'companies.name', 'companies.type')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // ── 10. Pemakaian voucher (periode) ──────────────────────────
        $topDiscounts = SubscriptionInvoice::join('subscription_discounts', 'subscription_discounts.id', '=', 'subscription_invoices.discount_id')
            ->where('subscription_invoices.status', 'paid')
            ->where('subscription_invoices.paid_at', '>=', $periodStart)
            ->select(
                'subscription_discounts.id',
                'subscription_discounts.name',
                'subscription_discounts.code',
                DB::raw('count(*) as used'),
                DB::raw('sum(subscription_invoices.discount_amount) as total_discount')
            )
            ->groupBy('subscription_discounts.id', 'subscription_discounts.name', 'subscription_discounts.code')
            ->orderByDesc('used')
            ->limit(5)
            ->get();

        return view('pages.superadmin.analytics.index', [
            'months'                => $months,
            'ranges'                => self::RANGES,
            'periodStart'           => $periodStart,
            'revenueThisMonth'      => $revenueThisMonth,
            'revenueLastMonth'      => $revenueLastMonth,
            'revenueGrowthPercent'  => $revenueGrowthPercent,
            'revenuePeriod'         => $revenuePeriod,
            'discountPeriod'        => $discountPeriod,
            'paidInvoicesPeriod'    => $paidInvoicesPeriod,
            'payingTenants'         => $payingTenants,
            'avgPerInvoice'         => $avgPerInvoice,
            'mrr'                   => $mrr,
            'totalTenants'          => $totalTenants,
            'activeTenants'         => $activeTenants,
            'suspendedTenants'      => $suspendedTenants,
            'tenantsByType'         => $tenantsByType,
            'conversionRate'        => $conversionRate,
            'everSubscribed'        => $everSubscribed,
            'everPaid'              => $everPaid,
            'subscriptionsByStatus' => $subscriptionsByStatus,
            'pendingInvoices'       => $pendingInvoices,
            'overdueInvoices'       => $overdueInvoices,
            'invoicesByStatus'      => $invoicesByStatus,
            'trend'                 => $trend,
            'revenueByPlan'         => $revenueByPlan,
            'revenueByType'         => $revenueByType,
            'topTenants'            => $topTenants,
            'topDiscounts'          => $topDiscounts,
        ]);
    }
}
