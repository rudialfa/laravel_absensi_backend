<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Tampilkan form login.
     */
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect($this->redirectPath(Auth::user()));
        }

        return view('pages.auth.auth-login');
    }

    /**
     * Proses login.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password salah.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        // Guard: pastikan user punya context yang valid
        // (superadmin tanpa company, atau user dengan company)
        if (! $user->company_id && $user->role !== 'superadmin') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda belum terhubung ke perusahaan/pesantren manapun.',
            ]);
        }

        // Kalau punya company, pastikan company masih aktif
        if ($user->company && isset($user->company->is_active) && ! $user->company->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Akun perusahaan Anda sedang tidak aktif. Hubungi admin.',
            ]);
        }

        return redirect()->intended($this->redirectPath($user));
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.form');
    }

    /**
     * Tentukan tujuan redirect berdasarkan context user.
     */
    private function redirectPath($user): string
    {
        // Superadmin — tidak punya company
        if (! $user->company_id && $user->role === 'superadmin') {
            return route('superadmin.dashboard');
        }

        $type = $user->company?->type;
        $role = $user->role;

        return match (true) {
            $type === 'company'   && $role === 'hr'       => route('company.dashboard'),
            $type === 'company'   && $role === 'employee' => route('employee.dashboard'),
            $type === 'pesantren' && $role === 'ustadz'   => route('pesantren.dashboard'),
            $type === 'pesantren' && $role === 'santri'   => route('santri.dashboard'),
            $type === 'school'    && $role === 'teacher'  => route('school.dashboard'),
            $type === 'school'    && $role === 'student'  => route('student.dashboard'),
            default => route('login.form'),
        };
    }
}
