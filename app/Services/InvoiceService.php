<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionDiscount;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function create(Company $company, SubscriptionPlan $plan, ?string $discountCode = null): SubscriptionInvoice
    {
        return DB::transaction(function () use ($company, $plan, $discountCode) {
            $subtotal       = (float) $plan->price;
            $discountAmount = 0;
            $discount       = null;

            if ($discountCode) {
                $discount = SubscriptionDiscount::where('code', $discountCode)->first();

                if (! $discount || ! $discount->isValid()) {
                    throw new \Exception('Kode diskon tidak valid atau sudah tidak berlaku.');
                }

                if ($discount->plan_id && $discount->plan_id !== $plan->id) {
                    throw new \Exception('Kode diskon tidak berlaku untuk paket ini.');
                }

                $discountAmount = $discount->calculateDiscount($subtotal);
            }

            $totalAmount = max(0, $subtotal - $discountAmount);

            // 1 company = 1 baris company_subscriptions (upsert kalau belum ada)
            $subscription = $company->subscriptions()->first() ?? CompanySubscription::create([
                'company_id' => $company->id,
                'plan_id'    => $plan->id,
                'status'     => 'expired', // belum bayar apa-apa, aktif setelah lunas
                'started_at' => now(),
                'expires_at' => now(),
            ]);

            $invoice = SubscriptionInvoice::create([
                'invoice_number'  => SubscriptionInvoice::generateNumber(),
                'company_id'      => $company->id,
                'subscription_id' => $subscription->id,
                'plan_id'         => $plan->id,
                'discount_id'     => $discount?->id,
                'subtotal'        => $subtotal,
                'discount_amount' => $discountAmount,
                'total_amount'    => $totalAmount,
                'status'          => 'pending',
                'issued_at'       => now(),
                'due_at'          => now()->addHours(24),
            ]);

            $discount?->incrementUsage();

            return $invoice;
        });
    }

    public function getPendingInvoice(Company $company): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::where('company_id', $company->id)
            ->pending()
            ->with('vaPayment')
            ->latest('issued_at')
            ->first();
    }

    public function getHistory(Company $company)
    {
        return SubscriptionInvoice::where('company_id', $company->id)
            ->with(['plan', 'vaPayment'])
            ->latest('issued_at')
            ->paginate(15);
    }

    public function expireOverdueInvoices(): int
    {
        $count = 0;

        SubscriptionInvoice::overdue()->get()->each(function (SubscriptionInvoice $invoice) use (&$count) {
            $invoice->markAsExpired();
            $invoice->vaPayment?->markAsExpired();
            $count++;
        });

        return $count;
    }
}
