<?php

namespace App\View\Composers;

use App\Models\SubscriptionPlan;
use App\Models\VaPayment;
use Illuminate\View\View;

/**
 * Dipakai untuk share data yang dibutuhkan komponen bersama di layout
 * superadmin (misal: dropdown filter plan di navbar, badge notifikasi
 * VA payment pending) — supaya SETIAP controller superadmin tidak perlu
 * manual passing $plans / $vaPayments satu-satu ke view.
 */
class SuperAdminGlobalDataComposer
{
    public function compose(View $view): void
    {
        $data = $view->getData();

        // Cuma isi kalau controller BELUM kirim $plans sendiri,
        // supaya tidak menimpa data spesifik (misal dropdown form voucher).
        if (! array_key_exists('plans', $data)) {
            $view->with('plans', SubscriptionPlan::where('is_active', true)
                ->orderBy('sort_order')
                ->get());
        }

        if (! array_key_exists('vaPayments', $data)) {
            $view->with('vaPayments', VaPayment::with('company')
                ->where('status', 'pending')
                ->latest()
                ->limit(5)
                ->get());
        }
    }
}
