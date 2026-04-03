<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;


class AuthController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $user = User::with('company')->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Login gagal'], 401);
        }

        if (!$user->company) {
            return response()->json(['message' => 'User tidak terhubung ke organisasi'], 403);
        }

        // hapus token lama
        $user->tokens()->delete();
        $token = $user->createToken('auth')->plainTextToken;

        $type = $user->company->type;   // company, pesantren, hospital, school
        $role = $user->role;            // hr, employee, ustadz, santri, teacher, dll

        $dashboardKey = $type . '.' . $role;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'context' => [
                'app_type'  => $type,
                'role'      => $role,
                'dashboard' => $dashboardKey
            ]
        ]);
    }




    private function resolveDashboard($user)
    {
        return $user->company->type . '.' . $user->role;
    }

    // register organization
    public function registerOrganization(Request $request)
    {
        $request->validate([
            // company
            'org_name'   => 'required',
            'org_email'  => 'required|email|unique:companies,email',
            'address'    => 'required',
            'latitude'   => 'required',
            'longitude'  => 'required',
            'radius_km'  => 'required',
            'time_in'    => 'required',
            'time_out'   => 'required',
            // 'type'       => 'required|in:company,pesantren,school,hospital',
            // 'type' => 'required|in:company,pesantren,school,hospital,government,factory,retail,restaurant,training,organization,transport,remote,sports',
            'type' => 'required|in:company,pesantren,',


            // admin
            'admin_name'  => 'required',
            'admin_email' => 'required|email|unique:users,email',
            'password'    => 'required|min:6'
        ]);

        DB::beginTransaction();

        try {
            // 1. Buat company
            $company = Company::create([
                'name'      => $request->org_name,
                'email'     => $request->org_email,
                'address'   => $request->address,
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
                'radius_km' => $request->radius_km,
                'time_in'   => $request->time_in,
                'time_out'  => $request->time_out,
                'type'      => $request->type,
                'timezone'  => 'Asia/Jakarta'
            ]);

            // 2. Tentukan role admin berdasarkan type organisasi
            $adminRoleMap = [
                'company'   => 'hr',
                'pesantren' => 'ustadz',
                // 'school'    => 'teacher',
                // 'hospital'  => 'hr'
            ];

            $adminRole = $adminRoleMap[$request->type];

            // 3. Buat user admin
            $admin = User::create([
                'name'       => $request->admin_name,
                'email'      => $request->admin_email,
                'password'   => Hash::make($request->password),
                'role'       => $adminRole,         // 🔥 BUKAN company
                'company_id' => $company->id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Organisasi berhasil didaftarkan',
                'company' => $company,
                'admin'   => [
                    'id'    => $admin->id,
                    'name'  => $admin->name,
                    'email' => $admin->email,
                    'role'  => $admin->role
                ],
                'dashboard_key' => $company->type . '.' . $admin->role
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mendaftarkan organisasi',
                'error'   => $e->getMessage()
            ], 500);
        }
    }




    //logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response(['message' => 'Logged out Sucessfully'], 200);
    }

    //update image profile & face_embedding
    public function updateProfile(Request $request)
    {
        $request->validate([
            //  'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'face_embedding' => 'required',
        ]);

        $user = $request->user();
        //  $image = $request->file('image');
        $face_embedding = $request->face_embedding;

        //  //save image
        //  $image->storeAs('public/images', $image->hashName());
        //  $user->image_url = $image->hashName();
        $user->face_embedding = $face_embedding;
        $user->save();

        return response([
            'message' => 'Profile updated',
            'user' => $user,
        ], 200);
    }


    // me
    public function me(Request $request)
    {
        $user = $request->user();
        $company = null;

        if ($user->company_id) {
            $company = Company::find($user->company_id);
        }

        return response()->json([
            'user'    => $user,
            'company' => $company
        ]);
    }

    //update fcm_token
    public function updateFcmToken(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required',
        ]);

        $user = $request->user();
        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response([
            'message' => 'FCM token updated',
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // cek password lama
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Password lama tidak sesuai'
            ], 422);
        }

        // update password
        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Password berhasil diganti'
        ]);
    }

    public function show()
    {
        $user = auth()->user()->load('company');

        // ubah image_url jadi full URL
        if ($user->image_url) {
            $user->image_url = asset($user->image_url);
        }

        if ($user->company && $user->company->image_url) {
            $user->company->image_url = asset($user->company->image_url);
        }

        return response()->json([
            'status' => true,
            'message' => 'Profile user',
            'data' => $user,
        ]);
    }

    /**
     * UPDATE PROFILE
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'  => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // update field satu-satu
        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        if ($request->filled('email')) {
            $user->email = $request->email;
        }

        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }

        // upload image ke public/image/profile
        if ($request->hasFile('image')) {

            // hapus image lama jika ada
            if ($user->image_url && File::exists(public_path($user->image_url))) {
                File::delete(public_path($user->image_url));
            }

            $image = $request->file('image');
            $filename = time() . '_' . $image->hashName();
            $image->move(public_path('image/profile'), $filename);

            $user->image_url = 'image/profile/' . $filename;
        }

        $user->save();

        $user->load('company');

        return response()->json([
            'status'  => true,
            'message' => 'Profile berhasil diperbarui',
            'data'    => $user,
        ], 200);
    }


    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user  = User::where('email', $request->email)->first();
        $token = Str::random(64);

        // Simpan token (ter-hash) ke tabel password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email'      => $request->email,
                'token'      => Hash::make($token),
                'created_at' => now()
            ]
        );

        // Link mengarah ke form web buatan kita sendiri
        // $resetUrl = url('/reset-password-form?token=' . $token . '&email=' . urlencode($request->email));

        $resetUrl = config('app.url') . '/api/reset-form?token=' . $token . '&email=' . urlencode($request->email);

        Mail::send('emails.reset_password', [
            'resetUrl' => $resetUrl,
            'user'     => $user
        ], function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Reset Password - Absensi App');
        });

        return response()->json([
            'status'  => true,
            'message' => 'Link reset password telah dikirim ke email kamu'
        ]);
    }

    // -------------------------------------------
    // RESET PASSWORD — verifikasi token manual
    // -------------------------------------------
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required'
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // Token tidak ditemukan
        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Token tidak ditemukan'
            ], 422);
        }

        // Token expired (60 menit)
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'status'  => false,
                'message' => 'Token sudah expired, silakan minta link baru'
            ], 422);
        }

        // Token tidak cocok
        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'status'  => false,
                'message' => 'Token tidak valid'
            ], 422);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        $user->forceFill([
            'password'       => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Hapus semua token sanctum lama
        $user->tokens()->delete();

        // Hapus record reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Password berhasil direset, silakan login'
        ]);
    }
}
