<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tolak akses (dan logout) kalau akun yang sedang login sudah dinonaktifkan.
 * Dipakai di panel web: begitu staff dinonaktifkan, request berikutnya langsung keluar.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akun Anda telah dinonaktifkan.'], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login.form')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi superadmin lain.']);
        }

        return $next($request);
    }
}
