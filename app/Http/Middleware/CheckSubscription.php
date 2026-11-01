<?php

namespace App\Http\Middleware;

use App\Models\CompanySubscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci akses API kalau langganan perusahaan sudah habis.
 *
 *   trial / active  → boleh
 *   grace (3 hari)  → masih boleh (aplikasi menampilkan peringatan)
 *   expired / cancelled / belum pernah berlangganan → DIKUNCI (HTTP 402)
 *
 * Yang TIDAK pernah dikunci (tidak memakai middleware ini):
 *   /auth/*, /v1/subscriptions/*, /notifications/*, /support-tickets/*,
 *   /help-articles/*, /policies/*
 *
 * Status di database ikut dirapikan di sini (syncStatus), jadi kunci tetap
 * jalan walaupun scheduler belum sempat berjalan.
 */
class CheckSubscription
{
    /** Role yang tidak pernah dikunci (pengelola aplikasi). */
    private const EXEMPT_ROLES = ['superadmin', 'staff'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->company_id || in_array($user->role, self::EXEMPT_ROLES, true)) {
            return $next($request);
        }

        $subscription = CompanySubscription::where('company_id', $user->company_id)->first();

        if ($subscription) {
            $subscription->syncStatus();
        }

        $info = [
            'active'     => $subscription ? $subscription->isActive() : false,
            'status'     => $subscription?->status ?? 'none',
            'expires_at' => $subscription?->expires_at?->toIso8601String(),
        ];

        if ($info['active']) {
            return $next($request);
        }

        $isBilling = method_exists($user, 'isBillingManager') && $user->isBillingManager();

        if ($info['status'] === 'none') {
            $message = $isBilling
                ? 'Perusahaan belum berlangganan. Silakan mulai trial atau pilih paket di menu Langganan.'
                : 'Perusahaan belum berlangganan. Silakan hubungi HR / admin perusahaan.';
        } else {
            $message = $isBilling
                ? 'Langganan perusahaan sudah berakhir dan masa toleransi 3 hari sudah habis. Silakan perpanjang di menu Langganan.'
                : 'Akses aplikasi dikunci karena langganan perusahaan sudah berakhir. Silakan hubungi HR / admin perusahaan.';
        }

        return response()->json([
            'status'  => false,
            'success' => false,
            'code'    => 'subscription_locked',
            'message' => $message,
            'data'    => [
                'subscription_status' => $info['status'],
                'expires_at'          => $info['expires_at'],
                'is_billing_manager'  => $isBilling,
            ],
        ], 402);
    }
}
