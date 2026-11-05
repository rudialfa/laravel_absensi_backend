<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance yang diatur dari panel superadmin (Pengaturan Sistem).
 *
 * Saat aktif:
 *   - API (aplikasi HP & kiosk) → HTTP 503 JSON { code: "maintenance", message, data.until }
 *   - Web tenant               → halaman resources/views/errors/maintenance.blade.php (503)
 *
 * Tetap bisa diakses:
 *   - /login, /logout, semua /superadmin/*
 *   - superadmin yang sedang login (web)
 *   - IP yang didaftarkan di "IP pengecualian"
 *   - path pengecualian bawaan (callback/webhook pembayaran) + yang ditambahkan di pengaturan
 *
 * Beda dengan `php artisan down`: itu mematikan SEMUA termasuk panel superadmin.
 */
class CheckSystemMaintenance
{
    /** Path yang tidak pernah dikunci (pola Request::is). */
    private const ALWAYS_EXCEPT = [
        'login',
        'logout',
        'superadmin',
        'superadmin/*',
        'up',
        // Callback / webhook pembayaran (Midtrans dsb.) JANGAN dikunci,
        // supaya pembayaran yang masuk selama maintenance tetap tercatat.
        'api/*callback*',
        'api/*webhook*',
        'api/*notification-handler*',
        'api/*midtrans*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!SystemSetting::bool('maintenance_mode')) {
            return $next($request);
        }

        if ($this->isExcepted($request)) {
            return $next($request);
        }

        $until = null;
        if ($raw = SystemSetting::get('maintenance_until')) {
            try {
                $until = Carbon::parse($raw, 'Asia/Jakarta');
            } catch (\Throwable) {
                $until = null;
            }
        }

        $message = SystemSetting::get('maintenance_message')
            ?: 'Aplikasi sedang dalam perbaikan. Silakan coba beberapa saat lagi.';

        $headers = [];
        if ($until && $until->isFuture()) {
            $headers['Retry-After'] = max(60, now()->diffInSeconds($until));
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'status'  => false,
                'success' => false,
                'code'    => 'maintenance',
                'message' => $message,
                'data'    => [
                    'maintenance' => true,
                    'until'       => $until?->toIso8601String(),
                ],
            ], 503, $headers);
        }

        return response()->view('errors.maintenance', [
            'message' => $message,
            'until'   => $until,
            'appName' => SystemSetting::get('app_name', config('app.name')),
            'support' => SystemSetting::get('support_email'),
        ], 503, $headers);
    }

    private function isExcepted(Request $request): bool
    {
        if ($request->is(...self::ALWAYS_EXCEPT)) {
            return true;
        }

        $extra = SystemSetting::lines('maintenance_except_paths');
        if ($extra && $request->is(...array_map(fn($p) => ltrim($p, '/'), $extra))) {
            return true;
        }

        if (in_array($request->ip(), SystemSetting::lines('maintenance_allowed_ips'), true)) {
            return true;
        }

        // Superadmin yang sedang login di web tetap bisa membuka semua halaman
        $user = $request->hasSession() ? Auth::guard('web')->user() : null;
        if ($user && $user->company_id === null && in_array($user->role, ['superadmin', 'staff'], true)) {
            return true;
        }

        return false;
    }
}
