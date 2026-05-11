<?php

namespace App\Http\Controllers\Backend\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller {

    // Show login form
    public function showLoginForm() {
        return view('pages.auth.auth-login');
    }

        public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();



         // TAMBAHAN (amanin role dulu)
            $role = strtolower(trim($user->role));

            if ($role === 'employee') {
                return redirect()->route('employee.dashboard');
            }

            if ($role === 'santri') {
             return redirect()->route('santri.dashboard');
            }

            if ($role === 'ustadz') {
                return redirect()->route('ustadz.dashboard');
            }
            // Redirect berdasarkan role
            switch ($user->role) {
                case 'admin':
                    return redirect()->route('admin.dashboard');
                case 'company':
                    return redirect()->route('company.dashboard');
                case 'user':
                    return redirect()->route('user.dashboard');
                default:
                    Auth::logout();
                    return redirect('/')->with('error', 'Role tidak dikenali.');
            }
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

}