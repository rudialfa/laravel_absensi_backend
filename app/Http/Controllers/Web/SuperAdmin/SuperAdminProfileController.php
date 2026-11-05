<?php

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Profil akun yang sedang login (staff / superadmin).
 *
 *  - show()     : profil + 8 aktivitas terakhir milik sendiri.
 *  - update()   : ubah nama & email. Dicatat di Audit Log (before/after).
 *  - password() : ganti password sendiri (wajib password lama). Dicatat di Audit Log tanpa isi password.
 */
class SuperAdminProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $recent = AuditLog::where('user_id', $user->id)->latest('id')->limit(8)->get();

        return view('pages.superadmin.profile.show', [
            'user'   => $user,
            'recent' => $recent,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ], [], [
            'name'  => 'nama',
            'email' => 'email',
        ]);

        $before = ['name' => $user->name, 'email' => $user->email];
        $changed = array_filter($data, fn($value, $key) => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH);

        if (!$changed) {
            return back()->with('success', 'Tidak ada perubahan.');
        }

        $user->forceFill($changed)->save();

        AuditLog::record('update_profile', $user, 'Profil diubah: ' . implode(', ', array_keys($changed)), [
            'before' => array_intersect_key($before, $changed),
            'after'  => $changed,
        ]);

        return back()->with('success', 'Profil disimpan.');
    }

    public function password(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'password.different' => 'Password baru tidak boleh sama dengan password lama.',
        ], [
            'current_password' => 'password lama',
            'password'         => 'password baru',
        ]);

        $user->forceFill(['password' => Hash::make($request->input('password'))])->save();

        AuditLog::record('change_own_password', $user, 'Mengganti password sendiri');

        return back()->with('success', 'Password berhasil diganti.');
    }
}
