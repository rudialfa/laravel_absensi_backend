<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AppPolicyController extends Controller
{
    /**
     * List semua kebijakan yang SEDANG AKTIF (1 per tipe).
     * GET /api/policies
     */
    public function index()
    {
        $policies = AppPolicy::where('is_active', true)
            ->orderBy('type')
            ->get(['id', 'type', 'title', 'version', 'published_at']);

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil daftar kebijakan aktif',
            'data' => $policies,
        ]);
    }

    /**
     * Detail 1 kebijakan aktif berdasarkan tipe.
     * GET /api/policies/{type}  — contoh: /api/policies/privacy_policy
     */
    public function show(string $type)
    {
        $policy = AppPolicy::where('type', $type)
            ->where('is_active', true)
            ->first();

        if (! $policy) {
            return response()->json([
                'status' => false,
                'message' => 'Kebijakan tidak ditemukan atau belum dipublikasikan',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Berhasil mengambil detail kebijakan',
            'data' => $policy,
        ]);
    }
}
