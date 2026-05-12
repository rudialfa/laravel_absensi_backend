<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionDummySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companyId = 1;

        // ============================================================
        // 1. DISCOUNT
        // ============================================================
        DB::table('subscription_discounts')->upsert(
            [
                [
                    'name'                => 'Promo Launching',
                    'code'                => 'LAUNCH50',
                    'discount_type'       => 'fixed',
                    'discount_value'      => 50000,
                    'max_discount_amount' => null,
                    'plan_id'             => null, // berlaku semua paket
                    'valid_from'          => Carbon::now()->subMonths(6),
                    'valid_until'         => Carbon::now()->addMonths(6),
                    'max_usage'           => 100,
                    'used_count'          => 1,
                    'is_active'           => true,
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ],
                [
                    'name'                => 'Diskon Ramadan',
                    'code'                => 'RAMADAN20',
                    'discount_type'       => 'percent',
                    'discount_value'      => 20,
                    'max_discount_amount' => 50000,
                    'plan_id'             => null,
                    'valid_from'          => Carbon::now()->subMonths(3),
                    'valid_until'         => Carbon::now()->subMonths(2), // sudah expired
                    'max_usage'           => 50,
                    'used_count'          => 12,
                    'is_active'           => false,
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ],
            ],
            ['code'],
            ['name', 'discount_type', 'discount_value', 'is_active', 'used_count', 'updated_at']
        );

        $discountId = DB::table('subscription_discounts')
            ->where('code', 'LAUNCH50')
            ->value('id');

        // ============================================================
        // 2. COMPANY SUBSCRIPTION (status: active, paket 6 bulan)
        // ============================================================
        $planBiannual = DB::table('subscription_plans')
            ->where('slug', 'biannual')
            ->first();

        $planMonthly = DB::table('subscription_plans')
            ->where('slug', 'monthly')
            ->first();

        $planTrial = DB::table('subscription_plans')
            ->where('slug', 'trial')
            ->first();

        // Hapus subscription lama untuk company ini jika ada
        DB::table('company_subscriptions')
            ->where('company_id', $companyId)
            ->delete();

        $subscriptionId = DB::table('company_subscriptions')->insertGetId([
            'company_id'      => $companyId,
            'plan_id'         => $planBiannual->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subMonths(1),
            'expires_at'      => Carbon::now()->addMonths(5),
            'has_used_trial'  => true,
            'last_invoice_id' => null, // diupdate setelah invoice dibuat
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // ============================================================
        // 3. INVOICES
        // ============================================================

        // Invoice 1 — PAID (paket bulanan, pakai diskon LAUNCH50)
        // Dibayar 6 bulan lalu
        $inv1IssuedAt = Carbon::now()->subMonths(7);
        $inv1Id = DB::table('subscription_invoices')->insertGetId([
            'invoice_number'  => 'INV-' . $inv1IssuedAt->format('Ymd') . '-00001',
            'company_id'      => $companyId,
            'subscription_id' => $subscriptionId,
            'plan_id'         => $planTrial->id,
            'discount_id'     => null,
            'subtotal'        => 0,
            'discount_amount' => 0,
            'total_amount'    => 0,
            'status'          => 'paid',
            'issued_at'       => $inv1IssuedAt,
            'due_at'          => $inv1IssuedAt->copy()->addDay(),
            'paid_at'         => $inv1IssuedAt->copy()->addHours(2),
            'notes'           => 'Trial gratis 7 hari',
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // Invoice 2 — PAID (paket bulanan, pakai diskon LAUNCH50)
        // Dibayar 1 bulan lalu
        $inv2IssuedAt = Carbon::now()->subMonths(1)->subDays(2);
        $inv2Id = DB::table('subscription_invoices')->insertGetId([
            'invoice_number'  => 'INV-' . $inv2IssuedAt->format('Ymd') . '-00002',
            'company_id'      => $companyId,
            'subscription_id' => $subscriptionId,
            'plan_id'         => $planMonthly->id,
            'discount_id'     => $discountId,
            'subtotal'        => 99000,
            'discount_amount' => 50000,
            'total_amount'    => 49000,
            'status'          => 'paid',
            'issued_at'       => $inv2IssuedAt,
            'due_at'          => $inv2IssuedAt->copy()->addDay(),
            'paid_at'         => $inv2IssuedAt->copy()->addHours(3),
            'notes'           => null,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // Invoice 3 — PAID (upgrade ke 6 bulan)
        // Invoice aktif saat ini — dibayar 1 bulan lalu
        $inv3IssuedAt = Carbon::now()->subMonths(1);
        $inv3Id = DB::table('subscription_invoices')->insertGetId([
            'invoice_number'  => 'INV-' . $inv3IssuedAt->format('Ymd') . '-00003',
            'company_id'      => $companyId,
            'subscription_id' => $subscriptionId,
            'plan_id'         => $planBiannual->id,
            'discount_id'     => null,
            'subtotal'        => 499000,
            'discount_amount' => 0,
            'total_amount'    => 499000,
            'status'          => 'paid',
            'issued_at'       => $inv3IssuedAt,
            'due_at'          => $inv3IssuedAt->copy()->addDay(),
            'paid_at'         => $inv3IssuedAt->copy()->addHours(1),
            'notes'           => null,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // Invoice 4 — PENDING (perpanjang berikutnya, belum dibayar)
        $inv4IssuedAt = Carbon::now()->subHours(2);
        $inv4Id = DB::table('subscription_invoices')->insertGetId([
            'invoice_number'  => 'INV-' . $inv4IssuedAt->format('Ymd') . '-00004',
            'company_id'      => $companyId,
            'subscription_id' => $subscriptionId,
            'plan_id'         => $planBiannual->id,
            'discount_id'     => null,
            'subtotal'        => 499000,
            'discount_amount' => 0,
            'total_amount'    => 499000,
            'status'          => 'pending',
            'issued_at'       => $inv4IssuedAt,
            'due_at'          => $inv4IssuedAt->copy()->addDay(),
            'paid_at'         => null,
            'notes'           => null,
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // Invoice 5 — EXPIRED (pernah buat tapi tidak dibayar)
        $inv5IssuedAt = Carbon::now()->subMonths(2);
        $inv5Id = DB::table('subscription_invoices')->insertGetId([
            'invoice_number'  => 'INV-' . $inv5IssuedAt->format('Ymd') . '-00005',
            'company_id'      => $companyId,
            'subscription_id' => $subscriptionId,
            'plan_id'         => $planMonthly->id,
            'discount_id'     => null,
            'subtotal'        => 99000,
            'discount_amount' => 0,
            'total_amount'    => 99000,
            'status'          => 'expired',
            'issued_at'       => $inv5IssuedAt,
            'due_at'          => $inv5IssuedAt->copy()->addDay(),
            'paid_at'         => null,
            'notes'           => 'VA kadaluarsa, tidak dibayar',
            'created_at'      => Carbon::now(),
            'updated_at'      => Carbon::now(),
        ]);

        // ============================================================
        // 4. VA PAYMENTS
        // Untuk invoice pending (inv4) dan expired (inv5)
        // ============================================================

        // VA untuk invoice PENDING
        DB::table('va_payments')->upsert(
            [
                [
                    'invoice_id'         => $inv4Id,
                    'company_id'         => $companyId,
                    'bank'               => 'bca',
                    'va_number'          => '88082026000400001',
                    'va_name'            => 'PT. CTA-BYTE',
                    'amount'             => 499000,
                    'status'             => 'pending',
                    'partner_service_id' => '  888820',
                    'customer_no'        => '26000400001',
                    'inquiry_request_id' => null,
                    'payment_request_id' => null,
                    'bank_reference_no'  => null,
                    'created_va_at'      => Carbon::now()->subHours(2),
                    'expired_at'         => Carbon::now()->addHours(22),
                    'paid_at'            => null,
                    'bank_response'      => null,
                    'created_at'         => Carbon::now(),
                    'updated_at'         => Carbon::now(),
                ],
                [
                    'invoice_id'         => $inv5Id,
                    'company_id'         => $companyId,
                    'bank'               => 'mandiri',
                    'va_number'          => '88008202600500002',
                    'va_name'            => 'PT. CTA-BYTE',
                    'amount'             => 99000,
                    'status'             => 'expired',
                    'partner_service_id' => null,
                    'customer_no'        => null,
                    'inquiry_request_id' => null,
                    'payment_request_id' => null,
                    'bank_reference_no'  => null,
                    'created_va_at'      => Carbon::now()->subMonths(2),
                    'expired_at'         => Carbon::now()->subMonths(2)->addDay(),
                    'paid_at'            => null,
                    'bank_response'      => null,
                    'created_at'         => Carbon::now(),
                    'updated_at'         => Carbon::now(),
                ],
            ],
            ['invoice_id'],
            ['status', 'expired_at', 'updated_at']
        );

        // ============================================================
        // 5. Update last_invoice_id di subscription → invoice paid terakhir
        // ============================================================
        DB::table('company_subscriptions')
            ->where('id', $subscriptionId)
            ->update(['last_invoice_id' => $inv3Id]);

        $this->command->info('✅ SubscriptionDummySeeder selesai untuk company_id ' . $companyId);
        $this->command->info('   Subscription : active (5 bulan tersisa)');
        $this->command->info('   Invoice      : 3 paid, 1 pending (BCA VA aktif), 1 expired');
        $this->command->info('   Discount     : LAUNCH50 (Rp 50.000), RAMADAN20 (20%)');
    }
}
