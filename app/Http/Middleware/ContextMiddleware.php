<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContextMiddleware
{


    // kode 2
    public function handle(Request $request, Closure $next, $type, $role = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorized($request);
        }

        // Context khusus system (superadmin) — tidak terikat company
        if ($type === 'system') {
            if ($user->company_id !== null || $user->role !== $role) {
                return $this->wrongRole($request);
            }
            return $next($request);
        }

        // Cek company type (untuk company/pesantren/school)
        if (!$user->company || $user->company->type !== $type) {
            return $this->wrongContext($request);
        }

        // Sekolah/organisasi sedang dinonaktifkan — tolak walau token masih valid
        if (!$user->company->is_active) {
            return response()->json([
                'message' => 'Organisasi Anda sedang dinonaktifkan. Hubungi admin pusat.',
            ], 403);
        }

        // Jika ada role requirement
        if ($role && $user->role !== $role) {
            return $this->wrongRole($request);
        }

        return $next($request);
    }

    /**
     * Handle unauthorized access
     */
    private function unauthorized(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return redirect()->route('login.form')->with('error', 'Silakan login terlebih dahulu');
    }

    /**
     * Handle wrong context
     */
    private function wrongContext(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Wrong app context'], 403);
        }

        return redirect()->route('login.form')->with('error', 'Anda tidak memiliki akses ke halaman ini');
    }

    /**
     * Handle wrong role
     */
    private function wrongRole(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Wrong role'], 403);
        }

        return redirect()->route('login.form')->with('error', 'Role Anda tidak memiliki akses ke halaman ini');
    }
}
