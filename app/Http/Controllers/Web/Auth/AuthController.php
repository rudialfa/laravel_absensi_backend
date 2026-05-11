<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | SHOW LOGIN FORM
    |--------------------------------------------------------------------------
    */
    public function showLoginForm()
    {
        // Jika sudah login, langsung redirect ke dashboard yang sesuai
        if (Auth::check()) {
            return $this->redirectToDashboard(Auth::user());
        }

        return view('pages.auth.auth-login');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
    | Logika sama persis dengan API AuthController:
    |   1. Validasi email & password
    |   2. Cek user exists
    |   3. Cek user terhubung ke company/organisasi
    |   4. Redirect berdasarkan kombinasi company.type + user.role (dashboardKey)
    |--------------------------------------------------------------------------
    */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // Ambil user beserta relasinya (sama seperti API)
        $user = User::with('company')->where('email', $request->email)->first();

        // Cek user & password
        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'email' => 'Email atau password salah.',
            ])->withInput($request->only('email'));
        }

        // Cek user terhubung ke organisasi (sama seperti API)
        if (!$user->company) {
            return back()->withErrors([
                'email' => 'User tidak terhubung ke organisasi.',
            ])->withInput($request->only('email'));
        }

        // Login via session
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectToDashboard($user);
    }

    /*
    |--------------------------------------------------------------------------
    | REDIRECT TO DASHBOARD
    | Menggunakan dashboardKey = company.type + '.' + user.role
    | Sama persis dengan logika API (resolveDashboard)
    |--------------------------------------------------------------------------
    |
    | Map dashboardKey → route name:
    |
    |   admin.*          → super admin (tidak perlu company)
    |   company.hr       → /company/dashboard
    |   company.employee → /employee/dashboard
    |   pesantren.ustadz → /pesantren/dashboard
    |   pesantren.santri → /santri/dashboard
    |   school.teacher   → /school/dashboard
    |   school.student   → /student/dashboard
    |
    */
    private function redirectToDashboard(User $user)
    {
        // Super admin: tidak perlu context company
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        // Pastikan company tersedia
        if (!$user->company) {
            Auth::logout();
            return redirect()->route('login.form')->withErrors([
                'email' => 'Akun tidak terhubung ke organisasi.',
            ]);
        }

        $type = $user->company->type;   // company, pesantren, school, hospital
        $role = $user->role;            // hr, employee, ustadz, santri, teacher, dll
        $dashboardKey = $type . '.' . $role;

        $routeMap = [
            'company.hr'          => 'company.dashboard',
            'company.employee'    => 'employee.dashboard',
            'pesantren.ustadz'    => 'pesantren.dashboard',
            'pesantren.santri'    => 'santri.dashboard',
            'school.teacher'      => 'school.dashboard',
            'school.student'      => 'student.dashboard',
            'hospital.hr'         => 'hospital.dashboard',      // siapkan jika perlu
            'hospital.employee'   => 'hospital.emp.dashboard',  // siapkan jika perlu
        ];

        if (!isset($routeMap[$dashboardKey])) {
            // dashboardKey tidak dikenali — logout dan tampilkan error
            Auth::logout();
            return redirect()->route('login.form')->withErrors([
                'email' => 'Role tidak dikenali: ' . $dashboardKey,
            ]);
        }

        return redirect()->route($routeMap[$dashboardKey]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.form');
    }
}