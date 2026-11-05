<?php

namespace App\Http\Middleware;

use App\Models\AttendanceDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Device-Token');

        if (!$token) {
            return $this->unauthorized('Device token tidak ditemukan');
        }

        $device = AttendanceDevice::with('company:id,name,is_active')
            ->where('device_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$device) {
            return $this->unauthorized('Device token tidak valid atau device nonaktif');
        }

        // Sekolah yang disuspend superadmin tidak boleh tetap absen lewat kiosk.
        // (Token kiosk bukan token Sanctum, jadi tidak ikut terhapus saat tenant disuspend.)
        if (!$device->company || !$device->company->is_active) {
            return $this->unauthorized('Akun sekolah sedang dinonaktifkan. Hubungi admin sekolah.');
        }

        // Cukup catat last_seen_at maksimal 1x per menit, supaya tidak menulis DB di setiap request kiosk
        if (!$device->last_seen_at || $device->last_seen_at->lt(now()->subMinute())) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        $request->attributes->set('attendanceDevice', $device);

        return $next($request);
    }

    private function unauthorized(string $message)
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
        ], 401);
    }
}