<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\AppPolicy;
use App\Support\HelpCenterFormat;

class AppPolicyController extends Controller
{
    /**
     * Daftar kebijakan yang SEDANG AKTIF (1 per tipe), tanpa isi.
     * GET /api/policies
     */
    public function index()
    {
        $order = array_keys(HelpCenterFormat::POLICY_TYPES);

        $policies = AppPolicy::where('is_active', true)
            ->get()
            ->sortBy(fn($p) => array_search($p->type, $order) === false ? 99 : array_search($p->type, $order))
            ->values()
            ->map(fn($p) => HelpCenterFormat::policy($p, false));

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil daftar kebijakan',
            'data'    => $policies,
        ]);
    }

    /**
     * Isi kebijakan aktif berdasarkan tipe.
     * GET /api/policies/{type}   contoh: terms_of_service, privacy_policy
     */
    public function show(string $type)
    {
        $policy = AppPolicy::where('type', $type)
            ->where('is_active', true)
            ->latest('published_at')
            ->first();

        if (! $policy) {
            $label = HelpCenterFormat::POLICY_TYPES[$type] ?? 'Kebijakan';

            return response()->json([
                'status'  => false,
                'message' => "{$label} belum tersedia.",
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'message' => 'Berhasil mengambil kebijakan',
            'data'    => HelpCenterFormat::policy($policy),
        ]);
    }
}