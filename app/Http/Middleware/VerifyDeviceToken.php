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

        $device = AttendanceDevice::where('device_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$device) {
            return $this->unauthorized('Device token tidak valid atau device nonaktif');
        }

        $device->update(['last_seen_at' => now()]);

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
