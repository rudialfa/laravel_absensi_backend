<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;

class SubscriptionService
{
    public function startTrial(Company $company): CompanySubscription
    {
        $subscription = $company->subscriptions()->first();

        if ($subscription && $subscription->has_used_trial) {
            throw new \Exception('Perusahaan sudah pernah memakai trial.');
        }

        $trialPlan = SubscriptionPlan::where('is_free', true)
            ->where('is_active', true)
            ->firstOrFail();

        $data = [
            'plan_id'        => $trialPlan->id,
            'status'         => 'trial',
            'started_at'     => now(),
            'expires_at'     => now()->addDays($trialPlan->duration_days ?: 7),
            'has_used_trial' => true,
        ];

        if ($subscription) {
            $subscription->update($data);
            return $subscription->fresh();
        }

        return CompanySubscription::create(array_merge($data, ['company_id' => $company->id]));
    }

    public function getStatus(Company $company): array
    {
        $subscription = $company->subscriptions()->with('plan')->first();

        if (! $subscription) {
            return [
                'status'         => 'expired',
                'is_active'      => false,
                'days_remaining' => 0,
                'started_at'     => null,
                'expires_at'     => null,
                'plan'           => null,
            ];
        }

        $subscription->syncStatus();

        return [
            'status'         => $subscription->status,
            'is_active'      => $subscription->isActive(),
            'days_remaining' => $subscription->daysRemaining(),
            'started_at'     => $subscription->started_at->toIso8601String(),
            'expires_at'     => $subscription->expires_at->toIso8601String(),
            'plan'           => [
                'name'          => $subscription->plan->name,
                'slug'          => $subscription->plan->slug,
                'duration_days' => $subscription->plan->duration_days,
            ],
        ];
    }

    /**
     * Dipanggil setelah invoice dinyatakan lunas (dari webhook Midtrans).
     */
    public function activateFromInvoice(SubscriptionInvoice $invoice): CompanySubscription
    {
        $company      = $invoice->company;
        $plan         = $invoice->plan;
        $subscription = $company->subscriptions()->first();

        if (! $subscription) {
            $subscription = CompanySubscription::create([
                'company_id' => $company->id,
                'plan_id'    => $plan->id,
                'status'     => 'expired',
                'started_at' => now(),
                'expires_at' => now(),
            ]);
        }

        $subscription->activate($plan, $invoice);

        return $subscription->fresh();
    }
}
