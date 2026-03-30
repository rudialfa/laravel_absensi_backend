<?php

namespace App\Http\Controllers\Api\Ustadz;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PesantrenSantriController extends Controller
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
                'message' => 'Company ID tidak ditemukan pada akun ustadz',
            ], 422));
        }
        return (int) $companyId;
    }

    // ============================================================
    // INDEX — GET /api/pesantren/santri
    // Sejajar: HrCompanyEmployeeController::index()
    // Query: q, department (kelas/angkatan), position (kamar), per_page
    // ============================================================
    public function index(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $q = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->orderByDesc('id');

        // Search nama / email / phone
        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->where(function ($w) use ($keyword) {
                $w->where('name',  'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%")
                    ->orWhere('phone', 'like', "%$keyword%");
            });
        }

        // Filter kelas / angkatan (department)
        if ($request->filled('department')) {
            $q->where('department', $request->department);
        }

        // Filter kamar (position)
        if ($request->filled('position')) {
            $q->where('position', $request->position);
        }

        $perPage = (int) $request->get('per_page', 20);

        return response()->json([
            'status'  => true,
            'message' => 'List santri',
            'data'    => $q->paginate($perPage),
        ]);
    }

    // ============================================================
    // STORE — POST /api/pesantren/santri
    // Sejajar: HrCompanyEmployeeController::store()
    // ============================================================
    public function store(Request $request): JsonResponse
    {
        $this->ensureUstadz();

        $validated = $request->validate([
            'name'       => 'required|string|max:120',
            'email'      => 'required|email|max:180|unique:users,email',
            'phone'      => 'nullable|string|max:30',
            'position'   => 'nullable|string|max:120', // kamar
            'department' => 'nullable|string|max:120', // kelas / angkatan
            'password'   => 'required|string|min:6',
        ]);

        $santri = User::create([
            'company_id' => $this->companyId(),
            'role'       => 'santri',
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone']      ?? null,
            'position'   => $validated['position']   ?? null, // kamar
            'department' => $validated['department'] ?? null, // kelas / angkatan
            'password'   => Hash::make($validated['password']),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Santri berhasil ditambahkan',
            'data'    => $santri,
        ], 201);
    }

    // ============================================================
    // SHOW — GET /api/pesantren/santri/{id}
    // Sejajar: HrCompanyEmployeeController::show()
    // ============================================================
    public function show(int $id): JsonResponse
    {
        $this->ensureUstadz();

        $santri = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail santri',
            'data'    => $santri,
        ]);
    }

    // ============================================================
    // UPDATE — PUT /api/pesantren/santri/{id}
    // Sejajar: HrCompanyEmployeeController::update()
    // ============================================================
    public function update(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $santri = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->findOrFail($id);

        $validated = $request->validate([
            'name'       => 'sometimes|required|string|max:120',
            'email'      => [
                'sometimes',
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')->ignore($santri->id),
            ],
            'phone'      => 'sometimes|nullable|string|max:30',
            'position'   => 'sometimes|nullable|string|max:120', // kamar
            'department' => 'sometimes|nullable|string|max:120', // kelas / angkatan
            'password'   => 'sometimes|nullable|string|min:6',
        ]);

        if (array_key_exists('name',       $validated)) $santri->name       = $validated['name'];
        if (array_key_exists('email',      $validated)) $santri->email      = $validated['email'];
        if (array_key_exists('phone',      $validated)) $santri->phone      = $validated['phone'];
        if (array_key_exists('position',   $validated)) $santri->position   = $validated['position'];
        if (array_key_exists('department', $validated)) $santri->department = $validated['department'];
        if (!empty($validated['password'] ?? null))     $santri->password   = Hash::make($validated['password']);

        $santri->save();

        return response()->json([
            'status'  => true,
            'message' => 'Data santri berhasil diperbarui',
            'data'    => $santri,
        ]);
    }

    // ============================================================
    // DESTROY — DELETE /api/pesantren/santri/{id}
    // Sejajar: HrCompanyEmployeeController::destroy()
    // ============================================================
    public function destroy(int $id): JsonResponse
    {
        $this->ensureUstadz();

        $santri = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->findOrFail($id);

        $santri->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Santri berhasil dihapus',
        ]);
    }

    // ============================================================
    // ATTENDANCE — GET /api/pesantren/santri/{id}/attendance
    // Riwayat absensi santri tertentu
    // Query: month, year, per_page
    // ============================================================
    public function attendance(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $santri = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->findOrFail($id);

        $month   = (int) $request->get('month', now()->month);
        $year    = (int) $request->get('year',  now()->year);
        $perPage = (int) $request->get('per_page', 30);

        $history = Attendance::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', $santri->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->paginate($perPage);

        $items = collect($history->items())->map(fn($a) => [
            'date'                => (string) $a->date,
            'time_in'             => $a->time_in  ? (string) $a->time_in  : null,
            'time_out'            => $a->time_out ? (string) $a->time_out : null,
            'scheduled_in'        => $a->scheduled_in  ? (string) $a->scheduled_in  : null,
            'scheduled_out'       => $a->scheduled_out ? (string) $a->scheduled_out : null,
            'status'              => (string) ($a->status ?? 'absent'),
            'late_minutes'        => (int)  ($a->late_minutes        ?? 0),
            'early_leave_minutes' => (int)  ($a->early_leave_minutes ?? 0),
            'face_verified'       => (bool) ($a->face_verified       ?? false),
            'marked_by'           => $a->marked_by,
        ])->values()->toArray();

        // Summary bulan ini
        $allThisMonth = Attendance::where('company_id', $this->companyId())
            ->where('user_id', $santri->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat absensi santri',
            'santri'  => [
                'id'         => $santri->id,
                'name'       => $santri->name,
                'position'   => $santri->position,
                'department' => $santri->department,
            ],
            'summary' => [
                'month'               => $month,
                'year'                => $year,
                'total_hari'          => $allThisMonth->count(),
                'hadir'               => $allThisMonth->whereNotNull('time_in')->count(),
                'on_time'             => $allThisMonth->where('status', 'on_time')->count(),
                'late'                => $allThisMonth->where('status', 'late')->count(),
                'absent'              => $allThisMonth->whereNull('time_in')->count(),
                'total_late_minutes'  => (int) $allThisMonth->sum('late_minutes'),
                'total_early_minutes' => (int) $allThisMonth->sum('early_leave_minutes'),
            ],
            'data'    => $items,
            'meta'    => [
                'current_page' => $history->currentPage(),
                'last_page'    => $history->lastPage(),
                'per_page'     => $history->perPage(),
                'total'        => $history->total(),
            ],
        ]);
    }

    // ============================================================
    // PERMISSIONS — GET /api/pesantren/santri/{id}/permissions
    // Riwayat izin santri tertentu
    // Query: status (pending|approved|rejected), per_page
    // ============================================================
    public function permissions(Request $request, int $id): JsonResponse
    {
        $this->ensureUstadz();

        $santri = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->findOrFail($id);

        $q = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', $santri->id)
            ->orderBy('date_permission', 'desc');

        if ($request->filled('status')) {
            // is_approved: null=pending, true=approved, false=rejected
            match ($request->status) {
                'pending'  => $q->whereNull('is_approved'),
                'approved' => $q->where('is_approved', true),
                'rejected' => $q->where('is_approved', false),
                default    => null,
            };
        }

        $perPage = (int) $request->get('per_page', 20);
        $result  = $q->paginate($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'Riwayat izin santri',
            'santri'  => [
                'id'   => $santri->id,
                'name' => $santri->name,
            ],
            'data'    => $result,
        ]);
    }

    // ============================================================
    // EXPORT PDF — GET /api/pesantren/santri/export
    // Sejajar: HrCompanyEmployeeController::export()
    // Query: q, department, position
    // ============================================================
    public function export(Request $request)
    {
        $this->ensureUstadz();

        $q = User::query()
            ->where('company_id', $this->companyId())
            ->where('role', 'santri')
            ->orderBy('department')
            ->orderBy('name');

        if ($request->filled('q')) {
            $keyword = $request->q;
            $q->where(function ($w) use ($keyword) {
                $w->where('name',  'like', "%$keyword%")
                    ->orWhere('email', 'like', "%$keyword%")
                    ->orWhere('phone', 'like', "%$keyword%");
            });
        }

        if ($request->filled('department')) {
            $q->where('department', $request->department);
        }

        if ($request->filled('position')) {
            $q->where('position', $request->position);
        }

        $santriList = $q->get(['id', 'name', 'email', 'phone', 'position', 'department', 'created_at']);
        $byKelas    = $santriList->groupBy(fn($s) => $s->department ?? 'Tidak Ada Kelas');
        $company    = auth()->user()->company ?? (object)['name' => ''];
        $fileName   = 'daftar-santri-' . now()->format('Y-m-d') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.pesantren_santri', [
            'company'     => $company,
            'santriList'  => $santriList,
            'byKelas'     => $byKelas,
            'total'       => $santriList->count(),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions(['defaultFont' => 'sans-serif', 'isHtml5ParserEnabled' => true]);

        return $pdf->download($fileName);
    }
}
