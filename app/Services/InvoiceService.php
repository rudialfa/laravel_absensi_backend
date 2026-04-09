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
    // ============================================================
    // BUAT INVOICE
    // ============================================================

    /**
     * Buat invoice baru saat company memilih paket.
     *
     * Flow:
     * 1. Validasi plan & diskon
     * 2. Hitung subtotal, diskon, total
     * 3. Batalkan invoice pending lama (kalau ada)
     * 4. Buat invoice baru
     * 5. Return invoice
     *
     * @param  Company               $company
     * @param  SubscriptionPlan      $plan
     * @param  string|null           $discountCode  Kode voucher dari user (opsional)
     * @return SubscriptionInvoice
     *
     * @throws \Exception
     */
    public function create(
        Company $company,
        SubscriptionPlan $plan,
        ?string $discountCode = null
    ): SubscriptionInvoice {

        // Paket gratis (trial) tidak butuh invoice
        if ($plan->is_free) {
            throw new \Exception('Paket trial tidak memerlukan invoice pembayaran.');
        }

        // Ambil atau buat subscription record (boleh belum ada)
        $subscription = CompanySubscription::firstOrCreate(
            ['company_id' => $company->id],
            [
                'plan_id'        => $plan->id,
                'status'         => 'expired',  // belum aktif sampai dibayar
                'started_at'     => now(),
                'expires_at'     => now(),
                'has_used_trial' => $company->hasUsedTrial(),
            ]
        );

        // Cari diskon jika ada kode
        $discount       = null;
        $discountAmount = 0;

        if ($discountCode) {
            $discount = $this->resolveDiscount($discountCode, $plan);
            $discountAmount = $discount->calculateDiscount((float) $plan->price);
        }

        $subtotal    = (float) $plan->price;
        $totalAmount = max(0, $subtotal - $discountAmount);

        return DB::transaction(function () use (
            $company,
            $subscription,
            $plan,
            $discount,
            $subtotal,
            $discountAmount,
            $totalAmount
        ) {
            // Batalkan invoice pending lama supaya tidak numpuk
            $this->cancelPendingInvoices($company);

            // Buat invoice baru
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
                'due_at'          => now()->addHours(24), // batas bayar 24 jam
            ]);

            // Tambah counter pemakaian diskon
            if ($discount) {
                $discount->incrementUsage();
            }

            // Update last_invoice di subscription
            $subscription->update(['last_invoice_id' => $invoice->id]);

            return $invoice->load(['plan', 'discount']);
        });
    }

    // ============================================================
    // SELESAIKAN INVOICE (dipanggil VaPaymentService)
    // ============================================================

    /**
     * Tandai invoice sebagai lunas.
     * Dipanggil setelah payment flag dari bank dikonfirmasi.
     */
    public function markAsPaid(SubscriptionInvoice $invoice): void
    {
        if (! $invoice->isPending()) {
            throw new \Exception("Invoice {$invoice->invoice_number} tidak dalam status pending.");
        }

        $invoice->markAsPaid();
    }

    // ============================================================
    // EXPIRE INVOICE LAMA
    // ============================================================

    /**
     * Expire semua invoice pending yang sudah melewati due_at.
     * Dipanggil oleh scheduler command.
     * Return jumlah invoice yang di-expire.
     */
    public function expireOverdue(): int
    {
        $invoices = SubscriptionInvoice::query()
            ->where('status', 'pending')
            ->where('due_at', '<', now())
            ->get();

        foreach ($invoices as $invoice) {
            $invoice->markAsExpired();
            // Expire juga VA-nya jika ada
            $invoice->vaPayment?->markAsExpired();
        }

        return $invoices->count();
    }

    // ============================================================
    // HELPERS PRIVATE
    // ============================================================

    /**
     * Cari dan validasi diskon dari kode voucher.
     *
     * @throws \Exception  Jika kode tidak valid / expired / habis quota
     */
    private function resolveDiscount(string $code, SubscriptionPlan $plan): SubscriptionDiscount
    {
        $discount = SubscriptionDiscount::where('code', $code)->first();

        if (! $discount) {
            throw new \Exception("Kode voucher '{$code}' tidak ditemukan.");
        }

        if (! $discount->isValid()) {
            throw new \Exception("Kode voucher '{$code}' sudah tidak berlaku.");
        }

        // Pastikan diskon berlaku untuk plan yang dipilih
        if ($discount->plan_id && $discount->plan_id !== $plan->id) {
            throw new \Exception("Kode voucher '{$code}' tidak berlaku untuk paket ini.");
        }

        return $discount;
    }

    /**
     * Batalkan semua invoice pending milik company.
     * Dipanggil sebelum buat invoice baru supaya tidak dobel.
     */
    private function cancelPendingInvoices(Company $company): void
    {
        SubscriptionInvoice::where('company_id', $company->id)
            ->where('status', 'pending')
            ->each(function (SubscriptionInvoice $inv) {
                $inv->update(['status' => 'cancelled']);
                // Batalkan juga VA-nya
                $inv->vaPayment?->update(['status' => 'cancelled']);
            });
    }

    // ============================================================
    // QUERY HELPERS
    // ============================================================

    /**
     * Ambil histori invoice company (untuk halaman riwayat pembayaran).
     */
    public function getHistory(Company $company, int $perPage = 10)
    {
        return SubscriptionInvoice::where('company_id', $company->id)
            ->with(['plan', 'discount', 'vaPayment'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Ambil invoice pending aktif milik company (jika ada).
     */
    public function getPendingInvoice(Company $company): ?SubscriptionInvoice
    {
        return SubscriptionInvoice::where('company_id', $company->id)
            ->where('status', 'pending')
            ->where('due_at', '>', now())
            ->with(['plan', 'vaPayment'])
            ->latest()
            ->first();
    }
}
