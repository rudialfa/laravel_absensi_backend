<?php

namespace App\Http\Controllers\Api\HrCompany;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class HrCompanyPermissionController extends Controller
{

    // private function ensureHr(): void
    // {
    //     if (!auth()->check() || auth()->user()->role !== 'hr') {
    //         abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
    //     }
    // }

    // private function companyId(): int
    // {
    //     $companyId = auth()->user()->company_id ?? null;
    //     if (!$companyId) {
    //         abort(response()->json(['status' => false, 'message' => 'Company ID tidak ditemukan'], 422));
    //     }
    //     return (int) $companyId;
    // }

    // private function baseQuery(Request $request)
    // {
    //     $q = Permission::query()
    //         ->with('user')
    //         ->where('company_id', $this->companyId());

    //     // filter status: pending|approved|rejected
    //     if ($request->filled('status')) {
    //         $status = $request->status;
    //         if ($status === 'pending') $q->whereNull('is_approved');
    //         if ($status === 'approved') $q->where('is_approved', true);
    //         if ($status === 'rejected') $q->where('is_approved', false);
    //     }

    //     // optional search
    //     if ($request->filled('q')) {
    //         $keyword = $request->q;
    //         $q->whereHas('user', function ($u) use ($keyword) {
    //             $u->where('name', 'like', "%$keyword%")
    //                 ->orWhere('email', 'like', "%$keyword%");
    //         });
    //     }

    //     return $q;
    // }

    // public function index(Request $request)
    // {
    //     $this->ensureHr();

    //     $data = $this->baseQuery($request)
    //         ->orderByDesc('id')
    //         ->paginate(20);

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'List permission company',
    //         'data' => $data,
    //     ]);
    // }

    // public function show(Request $request, $id)
    // {
    //     $this->ensureHr();

    //     $perm = $this->baseQuery($request)
    //         ->where('id', $id)
    //         ->firstOrFail();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Detail permission',
    //         'data' => $perm,
    //     ]);
    // }

    // public function approve(Request $request, $id)
    // {
    //     $this->ensureHr();

    //     $perm = Permission::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('id', $id)
    //         ->firstOrFail();

    //     $perm->is_approved = true;
    //     $perm->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Permission disetujui',
    //         'data' => $perm->fresh('user'),
    //     ]);
    // }

    // public function reject(Request $request, $id)
    // {
    //     $this->ensureHr();

    //     $perm = Permission::query()
    //         ->where('company_id', $this->companyId())
    //         ->where('id', $id)
    //         ->firstOrFail();

    //     $perm->is_approved = false;
    //     $perm->save();

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Permission ditolak',
    //         'data' => $perm->fresh('user'),
    //     ]);
    // }

    // kode 2
    private function ensureHr(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'hr') {
            abort(response()->json(['status' => false, 'message' => 'Akses ditolak (khusus HR)'], 403));
        }
    }

    private function companyId(): int
    {
        $companyId = auth()->user()->company_id ?? null;
        if (!$companyId) {
            abort(response()->json(['status' => false, 'message' => 'Company ID tidak ditemukan'], 422));
        }
        return (int) $companyId;
    }

    private function baseQuery(Request $request)
    {
        $q = Permission::query()
            ->with('user')
            ->where('company_id', $this->companyId());

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'pending')   $q->whereNull('is_approved');
            if ($status === 'approved')  $q->where('is_approved', true);
            if ($status === 'rejected')  $q->where('is_approved', false);
        }

        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->whereHas('user', function ($u) use ($keyword) {
                $u->where('name', 'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%");
            });
        }

        return $q;
    }

    // ============================================================
    // GET /api/company/hr/permissions
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureHr();
        return response()->json([
            'status'  => true,
            'message' => 'List permission company',
            'data'    => $this->baseQuery($request)->orderByDesc('id')->paginate(20),
        ]);
    }

    // ============================================================
    // GET /api/company/hr/permissions/{id}
    // ============================================================
    public function show(Request $request, $id)
    {
        $this->ensureHr();
        return response()->json([
            'status'  => true,
            'message' => 'Detail permission',
            'data'    => $this->baseQuery($request)->where('id', $id)->firstOrFail(),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/permissions/{id}/approve
    // ============================================================
    public function approve(Request $request, $id)
    {
        $this->ensureHr();
        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('id', $id)
            ->firstOrFail();

        $perm->is_approved = true;
        $perm->save();

        return response()->json([
            'status'  => true,
            'message' => 'Permission disetujui',
            'data'    => $perm->fresh('user'),
        ]);
    }

    // ============================================================
    // POST /api/company/hr/permissions/{id}/reject
    // ============================================================
    public function reject(Request $request, $id)
    {
        $this->ensureHr();
        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('id', $id)
            ->firstOrFail();

        $perm->is_approved = false;
        $perm->save();

        return response()->json([
            'status'  => true,
            'message' => 'Permission ditolak',
            'data'    => $perm->fresh('user'),
        ]);
    }

    // ============================================================
    // EXPORT PDF — rekap izin/cuti karyawan
    // GET /api/company/hr/permissions/export
    //
    // Query params:
    //   status (optional) — pending|approved|rejected
    //   q      (optional) — search nama/email
    //   month  (optional) — 1-12
    //   year   (optional)
    //
    // Install: composer require barryvdh/laravel-dompdf
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureHr();

        $q = Permission::query()
            ->with('user')
            ->where('company_id', $this->companyId())
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'pending')  $q->whereNull('is_approved');
            if ($status === 'approved') $q->where('is_approved', true);
            if ($status === 'rejected') $q->where('is_approved', false);
        }

        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->whereHas('user', fn($u) =>
            $u->where('name', 'like', "%$keyword%")
                ->orWhere('email', 'like', "%$keyword%"));
        }

        if ($request->filled('month') && $request->filled('year')) {
            $q->whereMonth('created_at', $request->month)
                ->whereYear('created_at', $request->year);
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

        $bulanLabel = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $month       = $request->month;
        $year        = $request->year ?? now()->year;
        $periodLabel = $month
            ? ($bulanLabel[$month] ?? $month) . ' ' . $year
            : 'Semua Periode';

        $fileName = 'permissions-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.hr_permission', [
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
