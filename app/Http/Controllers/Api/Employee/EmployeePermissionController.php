<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class EmployeePermissionController extends Controller
{
    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus employee)',
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

        return $companyId;
    }

    private function format(Permission $p): array
    {
        return [
            'id'              => $p->id,
            'type'            => $p->type,
            'type_label'      => $p->type_label,
            'date_permission' => $p->date_permission?->toDateString(),
            'end_date'        => $p->end_date?->toDateString(),
            'start_time'      => $p->start_time ? substr($p->start_time, 0, 5) : null,
            'end_time'        => $p->end_time ? substr($p->end_time, 0, 5) : null,
            'reason'          => $p->reason,
            'image'           => $p->image,
            'image_url'       => $p->image ? asset($p->image) : null,
            'is_approved'     => $p->is_approved,
            'status'          => $p->status_label, // pending | approved | rejected
            'approved_by'     => $p->approver ? ['id' => $p->approver->id, 'name' => $p->approver->name] : null,
            'approved_at'     => $p->approved_at?->toDateTimeString(),
            'approval_note'   => $p->approval_note,
            'created_at'      => $p->created_at?->toDateTimeString(),
        ];
    }

    // =========================
    // LIST JENIS IZIN (untuk dropdown di aplikasi)
    // GET /api/company/employee/permissions/types
    // =========================
    public function types()
    {
        $this->ensureEmployee();

        $rules = [
            Permission::TYPE_TIDAK_MASUK      => ['needs_start_time' => false, 'needs_end_time' => false, 'multi_day' => true],
            Permission::TYPE_DATANG_TERLAMBAT => ['needs_start_time' => false, 'needs_end_time' => true,  'multi_day' => false],
            Permission::TYPE_PULANG_CEPAT     => ['needs_start_time' => true,  'needs_end_time' => false, 'multi_day' => false],
            Permission::TYPE_KELUAR_KANTOR    => ['needs_start_time' => true,  'needs_end_time' => true,  'multi_day' => false],
            Permission::TYPE_DINAS_LUAR       => ['needs_start_time' => false, 'needs_end_time' => false, 'multi_day' => true],
        ];

        $data = collect(Permission::TYPE_LABELS)->map(fn($label, $key) => array_merge(
            ['value' => $key, 'label' => $label],
            $rules[$key]
        ))->values();

        return response()->json([
            'status'  => true,
            'message' => 'Jenis izin',
            'data'    => $data,
        ]);
    }

    // =========================
    // LIST (paginate)
    // =========================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $query = Permission::query()
            ->with('approver:id,name')
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id());

        if ($request->filled('status')) {
            match ($request->status) {
                'pending'  => $query->whereNull('is_approved'),
                'approved' => $query->where('is_approved', true),
                'rejected' => $query->where('is_approved', false),
                default    => null,
            };
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $page = $query->orderByDesc('date_permission')->orderByDesc('id')->paginate(20);
        $page->getCollection()->transform(fn($p) => $this->format($p));

        return response()->json([
            'status'  => true,
            'message' => 'List permission employee',
            'data'    => $page,
        ]);
    }

    // =========================
    // STORE
    // multipart/form-data (karena ada lampiran opsional)
    // =========================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $validated = $request->validate([
            'type'            => ['required', Rule::in(Permission::TYPES)],
            'date_permission' => 'required|date|after_or_equal:' . Carbon::today()->subDays(7)->toDateString(),
            'end_date'        => [
                'nullable',
                'date',
                'after_or_equal:date_permission',
                Rule::prohibitedIf(!in_array($request->type, [Permission::TYPE_TIDAK_MASUK, Permission::TYPE_DINAS_LUAR], true)),
            ],
            'start_time'      => [
                'nullable',
                'date_format:H:i',
                Rule::requiredIf(in_array($request->type, [Permission::TYPE_PULANG_CEPAT, Permission::TYPE_KELUAR_KANTOR], true)),
            ],
            'end_time'        => [
                'nullable',
                'date_format:H:i',
                Rule::requiredIf(in_array($request->type, [Permission::TYPE_DATANG_TERLAMBAT, Permission::TYPE_KELUAR_KANTOR], true)),
            ],
            'reason'          => 'required|string|min:5|max:500',
            'image'           => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ], [
            'type.required'          => 'Jenis izin wajib dipilih.',
            'reason.required'        => 'Alasan izin wajib diisi.',
            'reason.min'             => 'Alasan izin minimal 5 karakter.',
            'start_time.required'    => 'Jam mulai wajib diisi untuk jenis izin ini.',
            'end_time.required'      => 'Jam selesai wajib diisi untuk jenis izin ini.',
            'end_date.prohibited'    => 'Tanggal selesai hanya untuk izin tidak masuk atau dinas luar.',
            'date_permission.after_or_equal' => 'Izin hanya bisa diajukan maksimal 7 hari ke belakang.',
        ]);

        if (
            $validated['type'] === Permission::TYPE_KELUAR_KANTOR
            && $validated['end_time'] <= $validated['start_time']
        ) {
            return response()->json([
                'status'  => false,
                'message' => 'Jam selesai harus setelah jam mulai.',
            ], 422);
        }

        // Cegah izin ganda dengan jenis sama di tanggal yang sama (pending / approved)
        $duplicate = Permission::where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->where('type', $validated['type'])
            ->where(fn($q) => $q->whereNull('is_approved')->orWhere('is_approved', true))
            ->activeOn($validated['date_permission'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda sudah punya izin jenis ini di tanggal tersebut.',
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $destinationPath = public_path('image/permission');

            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }

            $file     = $request->file('image');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($destinationPath, $fileName);

            $imagePath = 'image/permission/' . $fileName;
        }

        $perm = Permission::create([
            'company_id'      => $this->companyId(),
            'user_id'         => auth()->id(),
            'type'            => $validated['type'],
            'date_permission' => $validated['date_permission'],
            'end_date'        => $validated['end_date'] ?? null,
            'start_time'      => $validated['start_time'] ?? null,
            'end_time'        => $validated['end_time'] ?? null,
            'reason'          => $validated['reason'],
            'image'           => $imagePath,
            'is_approved'     => null, // pending, menunggu HR
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Izin berhasil diajukan, menunggu persetujuan HR',
            'data'    => $this->format($perm->fresh('approver')),
        ], 201);
    }

    // =========================
    // DETAIL
    // =========================
    public function show($id)
    {
        $this->ensureEmployee();

        $perm = Permission::query()
            ->with('approver:id,name')
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Detail permission',
            'data'    => $this->format($perm),
        ]);
    }

    // =========================
    // CANCEL (hanya yang masih pending / ditolak)
    // =========================
    public function cancel($id)
    {
        $this->ensureEmployee();

        $perm = Permission::query()
            ->where('company_id', $this->companyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // Berfungsi benar karena is_approved sekarang di-cast boolean di model
        if ($perm->is_approved === true) {
            return response()->json([
                'status'  => false,
                'message' => 'Tidak bisa dibatalkan, izin sudah disetujui HR',
            ], 422);
        }

        if ($perm->image && File::exists(public_path($perm->image))) {
            File::delete(public_path($perm->image));
        }

        $perm->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Izin dibatalkan',
        ]);
    }
}
