<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Prayer;
use Carbon\Carbon;

class PesantrenPrayerController extends Controller
{
    private function ensurePesantrenUser()
    {
        if (!auth()->check() || !in_array(auth()->user()->role, ['ustadz', 'santri'])) {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak'], 403));
        }
    }

    private function companyId()
    {
        return auth()->user()->company_id ?? null;
    }

    public function today()
    {
        $this->ensurePesantrenUser();
        $date = Carbon::today()->toDateString();

        $data = Prayer::where('company_id', $this->companyId())
            ->whereDate('date', $date)
            ->first();

        return response()->json(['status' => true, 'message' => "Prayer times $date", 'data' => $data]);
    }

    public function byDate($date)
    {
        $this->ensurePesantrenUser();

        $data = Prayer::where('company_id', $this->companyId())
            ->whereDate('date', $date)
            ->first();

        return response()->json(['status' => true, 'message' => "Prayer times $date", 'data' => $data]);
    }
}
