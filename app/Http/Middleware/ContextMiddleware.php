<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContextMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    // public function handle(Request $request, Closure $next, $type, $role = null): Response
    // {
    //     // $user = $request->user();

    //     // // cek company type
    //     // if ($user->company->type !== $type) {
    //     //     return response()->json(['message' => 'Wrong app context'], 403);
    //     // }

    //     // // jika ada role
    //     // if ($role && $user->role !== $role) {
    //     //     return response()->json(['message' => 'Wrong role'], 403);
    //     // }

    //     // return $next($request);

    //     // haddle api saja

    //     // versi web dan api
    //      $user = $request->user();

    //     // Cek apakah user ada
    //     if (!$user) {
    //         return $this->unauthorized($request);
    //     }

    //     // Cek company type
    //     if (!$user->company || $user->company->type !== $type) {
    //         return $this->wrongContext($request);
    //     }

    //     // Jika ada role requirement
    //     if ($role && $user->role !== $role) {
    //         return $this->wrongRole($request);
    //     }

    //     return $next($request);
    // }


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
