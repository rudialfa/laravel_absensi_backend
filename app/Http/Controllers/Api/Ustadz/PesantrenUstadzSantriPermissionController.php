<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PesantrenUstadzSantriPermissionController extends Controller
{
    // ============================================================
    // PRIVATE HELPERS
    // ============================================================
 
    private function ensureUstadz(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'ustadz') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus Ustadz)',
            ], 403));
        }
    }
 
    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json([
                'status'  => false,
                'message' => 'Company ID tidak ditemukan',
            ], 422));
        }
        return (int) $companyId;
    }
 
    /**
     * Base query — selalu scope ke company & role santri.
     * Sejajar: HrCompanyPermissionController::baseQuery()
     */
    private function baseQuery(Request $request)
    {
        $q = Permission::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->whereHas('user', fn($u) => $u->where('role', 'santri'));
 
        // Filter status: pending | approved | rejected
        if ($request->filled('status')) {
            match ($request->status) {
                'pending'  => $q->whereNull('is_approved'),
                'approved' => $q->where('is_approved', true),
                'rejected' => $q->where('is_approved', false),
                default    => null,
            };
        }
 
        // Search nama / email santri
        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->whereHas('user', fn($u) =>
                $u->where('name',  'like', "%$keyword%")
                  ->orWhere('email', 'like', "%$keyword%")
            );
        }
 
        return $q;
    }
 
    private function bulanLabel(int $month): string
    {
        return [
            1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
            4  => 'April',     5  => 'Mei',       6  => 'Juni',
            7  => 'Juli',      8  => 'Agustus',   9  => 'September',
            10 => 'Oktober',   11 => 'November',  12 => 'Desember',
        ][$month] ?? (string) $month;
    }
 
    // ============================================================
    // INDEX — GET /api/pesantren/permissions/santri
    // Sejajar: HrCompanyPermissionController::index()
    // Query: status (pending|approved|rejected), q, per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureUstadz();
 
        return response()->json([
            'status'  => true,
            'message' => 'List izin santri',
            'data'    => $this->baseQuery($request)
                ->orderByDesc('id')
                ->paginate((int) $request->get('per_page', 20)),
        ]);
    }
 
    // ============================================================
    // SHOW — GET /api/pesantren/permissions/santri/{id}
    // Sejajar: HrCompanyPermissionController::show()
    // ============================================================
    public function show(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();
 
        $perm = Permission::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->whereHas('user', fn($u) => $u->where('role', 'santri'))
            ->findOrFail($id);
 
        return response()->json([
            'status'  => true,
            'message' => 'Detail izin santri',
            'data'    => $perm,
        ]);
    }
 
    // ============================================================
    // APPROVE — POST /api/pesantren/permissions/santri/{id}/approve
    // Sejajar: HrCompanyPermissionController::approve()
    // ============================================================
    public function approve(int $id): JsonResponse
    {
        $this->ensureUstadz();
 
        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->whereHas('user', fn($u) => $u->where('role', 'santri'))
            ->findOrFail($id);
 
        // Cek sudah diproses sebelumnya
        if (!is_null($perm->is_approved)) {
            return response()->json([
                'status'  => false,
                'message' => 'Izin ini sudah ' . ($perm->is_approved ? 'disetujui' : 'ditolak') . ' sebelumnya.',
                'data'    => $perm->load('user'),
            ], 422);
        }
 
        $perm->is_approved = true;
        $perm->save();
 
        return response()->json([
            'status'  => true,
            'message' => 'Izin santri disetujui',
            'data'    => $perm->fresh('user'),
        ]);
    }
 
    // ============================================================
    // REJECT — POST /api/pesantren/permissions/santri/{id}/reject
    // Sejajar: HrCompanyPermissionController::reject()
    // ============================================================
    public function reject(int $id): JsonResponse
    {
        $this->ensureUstadz();
 
        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->whereHas('user', fn($u) => $u->where('role', 'santri'))
            ->findOrFail($id);
 
        // Cek sudah diproses sebelumnya
        if (!is_null($perm->is_approved)) {
            return response()->json([
                'status'  => false,
                'message' => 'Izin ini sudah ' . ($perm->is_approved ? 'disetujui' : 'ditolak') . ' sebelumnya.',
                'data'    => $perm->load('user'),
            ], 422);
        }
 
        $perm->is_approved = false;
        $perm->save();
 
        return response()->json([
            'status'  => true,
            'message' => 'Izin santri ditolak',
            'data'    => $perm->fresh('user'),
        ]);
    }
 
    // ============================================================
    // EXPORT PDF — GET /api/pesantren/permissions/santri/export
    // Sejajar: HrCompanyPermissionController::export()
    // Query: status, q, month, year
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();
 
        $q = Permission::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->whereHas('user', fn($u) => $u->where('role', 'santri'))
            ->orderByDesc('id');
 
        if ($request->filled('status')) {
            match ($request->status) {
                'pending'  => $q->whereNull('is_approved'),
                'approved' => $q->where('is_approved', true),
                'rejected' => $q->where('is_approved', false),
                default    => null,
            };
        }
 
        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->whereHas('user', fn($u) =>
                $u->where('name',  'like', "%$keyword%")
                  ->orWhere('email', 'like', "%$keyword%")
            );
        }
 
        if ($request->filled('month') && $request->filled('year')) {
            $q->whereMonth('created_at', $request->month)
              ->whereYear('created_at',  $request->year);
        } elseif ($request->filled('year')) {
            $q->whereYear('created_at', $request->year);
        }
 
        $permissions = $q->get();
 
        $stats = [
            'total'    => $permissions->count(),
            'pending'  => $permissions->whereNull('is_approved')->count(),
            'approved' => $permissions->where('is_approved', true)->count(),
            'rejected' => $permissions->where('is_approved', false)->count(),
        ];
 
        $month       = $request->month;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? $this->bulanLabel((int) $month) . ' ' . $year
            : 'Semua Periode';
 
        $fileName = 'izin-santri-' . now()->format('Y-m-d') . '.pdf';
 
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_permission', [
            'company'     => auth()->user()->company ?? (object)['name' => ''],
            'permissions' => $permissions,
            'stats'       => $stats,
            'periodLabel' => $periodLabel,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);
 
        return $pdf->download($fileName);
    }
}
