<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
 public function handle($request, Closure $next, $role)
    {
        if (!Auth::check()) {

            // API request
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // WEB request
            return redirect()->route('login.form');
        }

        // =========================
        // 2. ROLE TIDAK SESUAI
        // =========================
        if (Auth::user()->role !== $role) {

            // API request
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Forbidden. Role tidak diizinkan.'
                ], 403);
            }

            // WEB request
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}
