<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Leaves;
use App\Services\Notifiers\ApprovalNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class EmployeeLeaveController extends Controller
{
    public function __construct(private ApprovalNotifier $approvals) {}

    private function ensureEmployee(): void
    {
        if (!auth()->check() || auth()->user()->role !== 'employee') {
            abort(response()->json([
                'status'  => false,
                'message' => 'Akses ditolak (khusus employee)',
            ], 403));
        }
    }

    private function myQuery(Request $request)
    {
        $user = $request->user();

        return Leaves::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id);
    }

    // ============================================================
    // GET /api/company/employee/leaves
    // filter: status, from, to, per_page
    // ============================================================
    public function index(Request $request)
    {
        $this->ensureEmployee();

        $q = $this->myQuery($request)
            ->with(['approver:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('from'))   $q->whereDate('end_date', '>=', $request->from);
        if ($request->filled('to'))     $q->whereDate('start_date', '<=', $request->to);

        $page = $q->paginate((int) $request->get('per_page', 10));
        $page->getCollection()->transform(fn($l) => $l->toApiArray());

        return response()->json([
            'status'  => true,
            'message' => 'List leaves',
            'data'    => $page,
        ]);
    }

    // ============================================================
    // GET /api/company/employee/leaves/{id}
    // ============================================================
    public function show(Request $request, int $id)
    {
        $this->ensureEmployee();

        $leave = $this->myQuery($request)->with(['approver:id,name'])->findOrFail($id);

        return response()->json([
            'status'  => true,
            'message' => 'Leave detail',
            'data'    => $leave->toApiArray(),
        ]);
    }

    // ============================================================
    // POST /api/company/employee/leaves
    // field: start_date, end_date, type, reason (wajib),
    //        attachment (file gambar/PDF, opsional)
    // → notifikasi ke HR
    // ============================================================
    public function store(Request $request)
    {
        $this->ensureEmployee();

        $user = $request->user();

        $validated = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:' . Carbon::today()->subDays(30)->toDateString()],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'type'       => ['nullable', Rule::in(Leaves::TYPES)],
            'reason'     => ['required', 'string', 'min:5', 'max:1000'],
            'attachment' => ['nullable'],
        ], [
            'start_date.after_or_equal' => 'Cuti hanya bisa diajukan maksimal 30 hari ke belakang.',
            'end_date.after_or_equal'   => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'reason.required'           => 'Alasan cuti wajib diisi.',
            'reason.min'                => 'Alasan cuti minimal 5 karakter.',
        ]);

        // Cegah cuti tumpang tindih dengan cuti lain yang masih pending / disetujui
        $overlap = $this->myQuery($request)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $validated['end_date'])
            ->whereDate('end_date', '>=', $validated['start_date'])
            ->first();

        if ($overlap) {
            return response()->json([
                'status'  => false,
                'message' => 'Tanggal cuti bentrok dengan pengajuan cuti lain ('
                    . $overlap->start_date->format('d/m/Y') . ' – ' . $overlap->end_date->format('d/m/Y') . ').',
            ], 422);
        }

        // Lampiran: boleh file (multipart) atau path string (cara lama)
        $attachment = null;

        if ($request->hasFile('attachment')) {
            $request->validate([
                'attachment' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:3072'],
            ], [
                'attachment.mimes' => 'Lampiran harus JPG, PNG, atau PDF.',
                'attachment.max'   => 'Lampiran maksimal 3 MB.',
            ]);

            $dir = public_path('image/leaves');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $file     = $request->file('attachment');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $fileName);
            $attachment = 'image/leaves/' . $fileName;
        } elseif (is_string($request->attachment) && $request->attachment !== '') {
            $attachment = $request->attachment;
        }

        $leave = Leaves::create([
            'company_id' => $user->company_id,
            'user_id'    => $user->id,
            'start_date' => $validated['start_date'],
            'end_date'   => $validated['end_date'],
            'type'       => $validated['type'] ?? 'annual',
            'reason'     => $validated['reason'],
            'attachment' => $attachment,
            'status'     => 'pending',
        ]);

        // 🔔 Notifikasi ke HR
        $this->approvals->submitted(ApprovalNotifier::KIND_LEAVE, $leave);

        return response()->json([
            'status'  => true,
            'message' => 'Pengajuan cuti terkirim, menunggu persetujuan HR',
            'data'    => $leave->fresh('approver:id,name')->toApiArray(),
        ], 201);
    }

    // ============================================================
    // POST /api/company/employee/leaves/{id}/cancel
    // ============================================================
    public function cancel(Request $request, int $id)
    {
        $this->ensureEmployee();

        $leave = $this->myQuery($request)->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya cuti pending yang bisa dibatalkan.',
            ], 422);
        }

        $leave->update(['status' => 'canceled']);

        return response()->json([
            'status'  => true,
            'message' => 'Pengajuan cuti dibatalkan',
            'data'    => $leave->fresh('approver:id,name')->toApiArray(),
        ]);
    }
}
