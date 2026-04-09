<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    // ============================================================
    // TRIAL
    // ============================================================

    /**
     * Mulai trial gratis 7 hari untuk company baru.
     * Dipanggil saat company pertama kali register.
     *
     * @throws \Exception  Jika company sudah pernah trial
     */
    public function startTrial(Company $company): CompanySubscription
    {
        if ($company->hasUsedTrial()) {
            throw new \Exception('Company ini sudah pernah menggunakan masa trial.');
        }

        $trialPlan = SubscriptionPlan::where('slug', 'trial')->firstOrFail();

        // updateOrCreate agar aman jika dipanggil 2x
        return CompanySubscription::updateOrCreate(
            ['company_id' => $company->id],
            [
                'plan_id'        => $trialPlan->id,
                'status'         => 'trial',
                'started_at'     => now(),
                'expires_at'     => now()->addDays($trialPlan->duration_days),
                'has_used_trial' => true,
            ]
        );
    }

    // ============================================================
    // STATUS
    // ============================================================

    /**
     * Ambil subscription aktif milik company.
     */
    public function getActive(Company $company): ?CompanySubscription
    {
        return CompanySubscription::where('company_id', $company->id)
            ->whereIn('status', ['trial', 'active'])
            ->where('expires_at', '>', now())
            ->with(['plan', 'lastInvoice'])
            ->first();
    }

    /**
     * Apakah fitur boleh diakses? — dipanggil Middleware.
     */
    public function isAccessible(Company $company): bool
    {
        return $this->getActive($company) !== null;
    }

    /**
     * Info lengkap status untuk response API / dashboard Flutter.
     */
    public function getStatus(Company $company): array
    {
        $sub = CompanySubscription::where('company_id', $company->id)
            ->with('plan')
            ->latest()
            ->first();

        if (! $sub) {
            return [
                'status'         => 'no_subscription',
                'is_active'      => false,
                'days_remaining' => 0,
                'expires_at'     => null,
                'plan'           => null,
                'can_trial'      => ! $company->hasUsedTrial(),
            ];
        }

        return [
            'status'         => $sub->status,
            'is_active'      => $sub->isActive(),
            'days_remaining' => $sub->daysRemaining(),
            'expires_at'     => $sub->expires_at?->toIso8601String(),
            'plan'           => [
                'name'          => $sub->plan->name,
                'slug'          => $sub->plan->slug,
                'duration_days' => $sub->plan->duration_days,
                'price'         => $sub->plan->price,
            ],
            'can_trial'      => false,
        ];
    }

    // ============================================================
    // AKTIVASI — dipanggil setelah invoice lunas
    // ============================================================

    /**
     * Aktifkan / perpanjang subscription setelah payment flag dari bank.
     * Jika masih aktif → sambung dari expires lama (tidak mubazir).
     * Jika sudah expired → mulai dari sekarang.
     */
    public function activateFromInvoice(SubscriptionInvoice $invoice): CompanySubscription
    {
        $company = $invoice->company;
        $plan    = $invoice->plan;

        $sub = CompanySubscription::firstOrNew(
            ['company_id' => $company->id]
        );

        $base = ($sub->exists && $sub->isActive())
            ? $sub->expires_at->copy()
            : Carbon::now();

        $sub->fill([
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'started_at'      => $base,
            'expires_at'      => $base->copy()->addDays($plan->duration_days),
            'has_used_trial'  => true,
            'last_invoice_id' => $invoice->id,
        ])->save();

        return $sub->fresh(['plan']);
    }

    // ============================================================
    // EXPIRE — dipanggil scheduler command
    // ============================================================

    /**
     * Kunci semua subscription yang melewati expires_at.
     * Return jumlah baris yang di-update.
     */
    public function expireAll(): int
    {
        return CompanySubscription::query()
            ->whereIn('status', ['trial', 'active'])
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
