<?php

namespace App\Http\Controllers\Api\School\Kiosk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class KioskAuthController extends Controller
{
    /**
     * GET /api/kiosk/ping
     */
    public function ping(Request $request)
    {
        $device = $request->attributes->get('attendanceDevice');

        return response()->json([
            'status'  => true,
            'message' => 'Device aktif',
            'data'    => [
                'device_name'   => $device->name,
                'default_class' => $device->classRoom?->only(['id', 'name']),
                'company_id'    => $device->company_id,
            ],
        ]);
    }
}
